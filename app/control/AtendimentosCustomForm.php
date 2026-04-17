<?php
class AtendimentosCustomForm extends TPage
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

        $this->html = new THtmlRenderer('app/resources/barbearia/atendimentos_custom_form.html');
        $this->html->enableSection('main', [
            'page_title' => _t('Appointments'),
            'page_subtitle' => _t('Appointments report.'),
            'label_profissional' => _t('Professional'),
            'label_data_inicio' => _t('Start date'),
            'label_data_fim' => _t('End date'),
            'label_status' => _t('Status')
        ]);

        $panel = new TPanelGroup(_t('Appointments'));
        $panel->add($this->html);

        $vbox = new TVBox;
        $vbox->style = 'width: 100%';
        $vbox->add($panel);

        parent::add($vbox);
    }

    public static function onGetProfissionais($param)
    {
        try
        {
            TTransaction::open(self::DB);

            $criteria = new TCriteria;
            $criteria->setProperty('order', 'nome asc');
            $criteria->add(new TFilter('ativo', '=', 'Y'));

            $repository = new TRepository('Profissional');
            $rows = $repository->load($criteria, false);

            $data = [];
            if ($rows)
            {
                foreach ($rows as $row)
                {
                    $data[] = [
                        'id' => (int) $row->id,
                        'nome' => (string) $row->nome
                    ];
                }
            }

            TTransaction::close();

            self::jsonResponse($data);
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            self::jsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public static function onGetServicos($param)
    {
        self::jsonResponse([]);
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

    public static function onGetHorarios($param)
    {
        self::jsonResponse(['slots' => []]);
    }

    public static function onUpsert($param)
    {
        self::jsonResponse(['error' => true, 'message' => 'Operacao nao permitida.']);
    }

    public static function onDelete($param)
    {
        self::jsonResponse(['error' => true, 'message' => 'Operacao nao permitida.']);
    }

    public static function onCancel($param)
    {
        self::jsonResponse(['error' => true, 'message' => 'Operacao nao permitida.']);
    }

    public static function onConcluir($param)
    {
        self::jsonResponse(['error' => true, 'message' => 'Operacao nao permitida.']);
    }

    private static function horarioDentroAgenda(int $profissionalId, string $data, string $horaInicio, string $horaFim): bool
    {
        return false;
    }

    private static function gerarMovimentoCaixa(Agendamento $agendamento): void
    {
    }

    private static function timeToMinutes(string $value): int
    {
        return 0;
    }

    private static function loadRows(array $param): array
    {
        $filters = self::resolveListParams($param);
        $criteria = self::buildListCriteria($filters);

        $repository = new TRepository('Agendamento');
        $total = $repository->count(clone $criteria);

        $criteria = self::buildListCriteria($filters);
        $criteria->setProperty('order', "case status when 'Agendado' then 0 when 'Confirmado' then 1 when 'Em atendimento' then 2 when 'Concluido' then 3 when 'Cancelado' then 4 else 5 end, data_agendamento asc, hora_inicio asc");
        $criteria->setProperty('limit', $filters['per_page']);
        $criteria->setProperty('offset', ($filters['page'] - 1) * $filters['per_page']);

        $rows = $repository->load($criteria, false);

        $profRepo = new TRepository('Profissional');
        $servRepo = new TRepository('Servico');
        $profList = $profRepo->load(new TCriteria, false);
        $servList = $servRepo->load(new TCriteria, false);

        $profMap = [];
        if ($profList)
        {
            foreach ($profList as $prof)
            {
                $profMap[$prof->id] = $prof->nome;
            }
        }

        $servMap = [];
        if ($servList)
        {
            foreach ($servList as $serv)
            {
                $servMap[$serv->id] = $serv->nome;
            }
        }

        $data = [];
        if ($rows)
        {
            foreach ($rows as $row)
            {
                $data[] = [
                    'id' => (int) $row->id,
                    'profissional_id' => (int) $row->profissional_id,
                    'servico_id' => (int) $row->servico_id,
                    'profissional' => (string) ($profMap[$row->profissional_id] ?? ''),
                    'servico' => (string) ($servMap[$row->servico_id] ?? ''),
                    'data' => (string) $row->data_agendamento,
                    'data_formatada' => self::formatDate($row->data_agendamento),
                    'data_conclusao' => (string) $row->data_conclusao,
                    'data_conclusao_formatada' => self::formatDateTime($row->data_conclusao),
                    'hora_inicio' => (string) $row->hora_inicio,
                    'hora_fim' => (string) $row->hora_fim,
                    'status' => (string) $row->status,
                    'nome_cliente' => (string) $row->nome_cliente,
                    'telefone_cliente' => (string) $row->telefone_cliente,
                    'observacao' => (string) $row->observacao,
                    'valor_cobrado' => (float) ($row->valor_cobrado ?? 0),
                    'forma_pagamento' => (string) $row->forma_pagamento
                ];
            }
        }

        return [
            'rows' => $data,
            'total' => $total,
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'filters' => [
                'profissional_id' => $filters['profissional_id'],
                'data' => $filters['data']
            ]
        ];
    }

    private static function resolveListParams(array $param): array
    {
        $profissionalId = (int) ($param['profissional_id_list'] ?? $param['profissional_id'] ?? 0);
        $dataInicio = trim((string) ($param['data_inicio_list'] ?? $param['data_inicio'] ?? ''));
        $dataFim = trim((string) ($param['data_fim_list'] ?? $param['data_fim'] ?? ''));
        $status = trim((string) ($param['status_list'] ?? $param['status'] ?? ''));

        $dataInicio = Agendamento::normalizeDate($dataInicio);
        $dataFim = Agendamento::normalizeDate($dataFim);

        $page = max(1, (int) ($param['page'] ?? 1));
        $perPage = (int) ($param['per_page'] ?? 12);
        if ($perPage <= 0)
        {
            $perPage = 12;
        }

        return [
            'profissional_id' => $profissionalId,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'status' => $status,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    private static function buildListCriteria(array $filters): TCriteria
    {
        $criteria = new TCriteria;

        if (!empty($filters['data_inicio']))
        {
            $criteria->add(new TFilter('data_agendamento', '>=', $filters['data_inicio']));
        }
        if (!empty($filters['data_fim']))
        {
            $criteria->add(new TFilter('data_agendamento', '<=', $filters['data_fim']));
        }
        if (!empty($filters['profissional_id']))
        {
            $criteria->add(new TFilter('profissional_id', '=', $filters['profissional_id']));
        }
        if (!empty($filters['status']))
        {
            $criteria->add(new TFilter('status', '=', $filters['status']));
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

    private static function formatDate(?string $value): string
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

        return date('d/m/Y', $time);
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

    private static function jsonResponse($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
