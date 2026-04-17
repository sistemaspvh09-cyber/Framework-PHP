<?php
class MovimentoCaixaCustomForm extends TPage
{
    protected $html;

    private const DB = 'barbearia';

    public function __construct($param = null)
    {
        parent::__construct();

        if (!empty($param['target_container']))
        {
            $this->adianti_target_container = $param['target_container'];
        }

        $this->html = new THtmlRenderer('app/resources/barbearia/movimento_caixa_custom_form.html');
        $this->html->enableSection('main', [
            'page_title' => _t('Cash movement'),
            'page_subtitle' => _t('Track cash entries and exits.'),
            'label_data' => _t('Movement date'),
            'label_tipo' => _t('Type'),
            'label_descricao' => _t('Description'),
            'label_valor' => _t('Value'),
            'label_categoria' => _t('Category'),
            'btn_save' => _t('Save'),
            'btn_clear' => _t('Clear')
        ]);

        $panel = new TPanelGroup(_t('Cash movement'));
        $panel->add($this->html);

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($panel);

        parent::add($vbox);
    }

    public static function onGetState($param)
    {
        try
        {
            TTransaction::open(self::DB);

            $data = self::loadRows($param);

            TTransaction::close();

            self::jsonResponse($data);
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            self::jsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public static function onUpsert($param)
    {
        try
        {
            $payload = self::getPayload($param);

            $id = (int) ($payload['id'] ?? 0);
            $dataMov = trim((string) ($payload['data_movimento'] ?? ''));
            $dataMov = self::normalizeDateTime($dataMov);
            $tipo = trim((string) ($payload['tipo'] ?? ''));
            $descricao = trim((string) ($payload['descricao'] ?? ''));
            $valor = (string) ($payload['valor'] ?? '');
            $categoria = trim((string) ($payload['categoria'] ?? ''));

            if ($dataMov === '')
            {
                throw new RuntimeException('Informe a data.');
            }
            if ($tipo !== 'E' && $tipo !== 'S')
            {
                throw new RuntimeException('Selecione o tipo.');
            }
            if ($descricao === '')
            {
                throw new RuntimeException('Informe a descricao.');
            }
            if ($valor === '' || (float) $valor <= 0)
            {
                throw new RuntimeException('Informe o valor.');
            }

            TTransaction::open(self::DB);

            $object = $id > 0 ? new MovimentoCaixa($id) : new MovimentoCaixa;
            $object->data_movimento = $dataMov;
            $object->tipo = $tipo;
            $object->descricao = $descricao;
            $object->valor = (float) $valor;
            $object->categoria = $categoria;
            if ($id <= 0)
            {
                $object->created_at = date('Y-m-d H:i:s');
            }
            $object->store();

            $dataRows = self::loadRows(array_merge($param, $payload));

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'rows' => $dataRows['rows'],
                'total' => $dataRows['total'],
                'page' => $dataRows['page'],
                'per_page' => $dataRows['per_page'],
                'message' => 'Movimento salvo.'
            ]);
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            self::jsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public static function onDelete($param)
    {
        try
        {
            $id = (int) ($param['id'] ?? 0);
            if ($id <= 0)
            {
                throw new RuntimeException('Registro invalido.');
            }

            TTransaction::open(self::DB);

            $object = new MovimentoCaixa($id);
            $object->delete();

            $dataRows = self::loadRows($param);

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'rows' => $dataRows['rows'],
                'total' => $dataRows['total'],
                'page' => $dataRows['page'],
                'per_page' => $dataRows['per_page'],
                'message' => 'Movimento removido.'
            ]);
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            self::jsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    private static function loadRows(array $param): array
    {
        $filters = self::resolveListParams($param);
        $criteria = self::buildListCriteria($filters);

        $repository = new TRepository('MovimentoCaixa');
        $total = $repository->count(clone $criteria);

        $criteria = self::buildListCriteria($filters);
        $criteria->setProperty('order', 'data_movimento desc, id desc');
        $criteria->setProperty('limit', $filters['per_page']);
        $criteria->setProperty('offset', ($filters['page'] - 1) * $filters['per_page']);

        $rows = $repository->load($criteria, false);

        $data = [];
        if ($rows)
        {
            foreach ($rows as $row)
            {
                $dataMovimento = (string) $row->data_movimento;
                $dataMovimentoDate = self::extractDate($dataMovimento);
                $data[] = [
                    'id' => (int) $row->id,
                    'data_movimento' => $dataMovimento,
                    'data_movimento_date' => $dataMovimentoDate,
                    'data_formatada' => self::formatDateTime($dataMovimento),
                    'tipo' => (string) $row->tipo,
                    'descricao' => (string) $row->descricao,
                    'valor' => (float) ($row->valor ?? 0),
                    'categoria' => (string) $row->categoria
                ];
            }
        }

        return [
            'rows' => $data,
            'total' => $total,
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'filters' => [
                'data_inicio' => $filters['data_inicio'],
                'data_fim' => $filters['data_fim'],
                'tipo' => $filters['tipo']
            ]
        ];
    }

    private static function resolveListParams(array $param): array
    {
        $dataInicio = trim((string) ($param['data_inicio_list'] ?? $param['data_inicio'] ?? ''));
        $dataFim = trim((string) ($param['data_fim_list'] ?? $param['data_fim'] ?? ''));
        $tipo = trim((string) ($param['tipo_list'] ?? $param['tipo'] ?? ''));

        $dataInicio = Agendamento::normalizeDate($dataInicio);
        $dataFim = Agendamento::normalizeDate($dataFim);

        $page = max(1, (int) ($param['page'] ?? 1));
        $perPage = (int) ($param['per_page'] ?? 12);
        if ($perPage <= 0)
        {
            $perPage = 12;
        }

        return [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'tipo' => $tipo,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    private static function buildListCriteria(array $filters): TCriteria
    {
        $criteria = new TCriteria;

        if (!empty($filters['data_inicio']))
        {
            $criteria->add(new TFilter('data_movimento', '>=', $filters['data_inicio'] . ' 00:00:00'));
        }
        if (!empty($filters['data_fim']))
        {
            $criteria->add(new TFilter('data_movimento', '<=', $filters['data_fim'] . ' 23:59:59'));
        }
        if (!empty($filters['tipo']))
        {
            $criteria->add(new TFilter('tipo', '=', $filters['tipo']));
        }

        return $criteria;
    }

    private static function getPayload(array $param): array
    {
        $payloadJson = (string) ($param['payload_json'] ?? '{}');
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload))
        {
            throw new RuntimeException('Payload invalido.');
        }

        return $payload;
    }

    private static function formatDateTime(?string $value): string
    {
        $value = (string) $value;
        if ($value === '')
        {
            return '-';
        }

        $time = strtotime($value);
        if ($time === false)
        {
            return $value;
        }

        return date('d/m/Y H:i', $time);
    }

    private static function normalizeDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '')
        {
            return '';
        }

        $value = str_replace('T', ' ', $value);
        $parts = preg_split('/\s+/', $value);
        $datePart = (string) ($parts[0] ?? '');
        $timePart = (string) ($parts[1] ?? '');

        $datePart = Agendamento::normalizeDate($datePart);
        if ($datePart === '')
        {
            return '';
        }

        if ($timePart === '')
        {
            $timePart = '00:00';
        }

        $timePart = Agendamento::normalizeTime($timePart);
        if ($timePart === '')
        {
            $timePart = '00:00:00';
        }

        return $datePart . ' ' . $timePart;
    }

    private static function extractDate(string $value): string
    {
        $value = trim($value);
        if ($value === '')
        {
            return '';
        }

        $parts = preg_split('/\s+/', $value);
        $datePart = (string) ($parts[0] ?? '');
        $datePart = Agendamento::normalizeDate($datePart);
        if ($datePart !== '')
        {
            return $datePart;
        }

        $time = strtotime($value);
        if ($time === false)
        {
            return '';
        }

        return date('Y-m-d', $time);
    }

    private static function jsonResponse($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
