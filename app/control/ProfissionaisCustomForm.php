<?php
class ProfissionaisCustomForm extends TPage
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

        $this->html = new THtmlRenderer('app/resources/barbearia/profissionais_custom_form.html');
        $this->html->enableSection('main', [
            'page_title' => _t('Professionals'),
            'page_subtitle' => _t('Manage your barbershop professionals.'),
            'label_nome' => _t('Name'),
            'label_telefone' => _t('Phone'),
            'label_ativo' => _t('Active'),
            'btn_save' => _t('Save'),
            'btn_clear' => _t('Clear')
        ]);

        $panel = new TPanelGroup(_t('Professionals'));
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
            $nome = trim((string) ($payload['nome'] ?? ''));
            $telefone = trim((string) ($payload['telefone'] ?? ''));
            $ativo = trim((string) ($payload['ativo'] ?? 'Y'));

            if ($nome === '')
            {
                throw new RuntimeException('Informe o nome.');
            }

            if ($ativo === '')
            {
                $ativo = 'Y';
            }

            TTransaction::open(self::DB);

            $object = $id > 0 ? new Profissional($id) : new Profissional;
            $object->nome = $nome;
            $object->telefone = $telefone;
            $object->ativo = $ativo;
            $object->updated_at = date('Y-m-d H:i:s');
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
                'message' => 'Profissional salvo.'
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

            $criteria = new TCriteria;
            $criteria->add(new TFilter('profissional_id', '=', $id));
            $repo = new TRepository('ProfissionalServico');
            if ($repo->count($criteria) > 0)
            {
                throw new RuntimeException('Este profissional possui servicos vinculados. Remova os vinculos antes de excluir.');
            }

            $object = new Profissional($id);
            $object->delete();

            $dataRows = self::loadRows($param);

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'rows' => $dataRows['rows'],
                'total' => $dataRows['total'],
                'page' => $dataRows['page'],
                'per_page' => $dataRows['per_page'],
                'message' => 'Profissional removido.'
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

        $repository = new TRepository('Profissional');
        $total = $repository->count(clone $criteria);

        $criteria = self::buildListCriteria($filters);
        $criteria->setProperty('order', 'nome asc');
        $criteria->setProperty('limit', $filters['per_page']);
        $criteria->setProperty('offset', ($filters['page'] - 1) * $filters['per_page']);

        $rows = $repository->load($criteria, false);

        $data = [];
        if ($rows)
        {
            foreach ($rows as $row)
            {
                $data[] = [
                    'id' => (int) $row->id,
                    'nome' => (string) $row->nome,
                    'telefone' => (string) $row->telefone,
                    'ativo' => (string) $row->ativo
                ];
            }
        }

        return [
            'rows' => $data,
            'total' => $total,
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'filters' => [
                'nome' => $filters['nome'],
                'ativo' => $filters['ativo']
            ]
        ];
    }

    private static function resolveListParams(array $param): array
    {
        $nome = trim((string) ($param['nome_list'] ?? $param['nome'] ?? ''));
        $ativo = trim((string) ($param['ativo_list'] ?? $param['ativo'] ?? ''));

        $page = max(1, (int) ($param['page'] ?? 1));
        $perPage = (int) ($param['per_page'] ?? 12);
        if ($perPage <= 0)
        {
            $perPage = 12;
        }

        return [
            'nome' => $nome,
            'ativo' => $ativo,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    private static function buildListCriteria(array $filters): TCriteria
    {
        $criteria = new TCriteria;

        if (!empty($filters['nome']))
        {
            $criteria->add(new TFilter('nome', 'like', '%' . $filters['nome'] . '%'));
        }

        if (!empty($filters['ativo']))
        {
            $criteria->add(new TFilter('ativo', '=', $filters['ativo']));
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

    private static function jsonResponse($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
