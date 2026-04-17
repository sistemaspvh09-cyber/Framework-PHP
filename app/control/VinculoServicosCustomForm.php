<?php
class VinculoServicosCustomForm extends TPage
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

        $this->html = new THtmlRenderer('app/resources/barbearia/vinculo_servicos_custom_form.html');
        $this->html->enableSection('main', [
            'page_title' => _t('Service links'),
            'page_subtitle' => _t('Link professionals to services.'),
            'label_profissional' => _t('Professional'),
            'label_servico' => _t('Service'),
            'btn_save' => _t('Save'),
            'btn_clear' => _t('Clear')
        ]);

        $panel = new TPanelGroup(_t('Service links'));
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

            $repo = new TRepository('Profissional');
            $rows = $repo->load($criteria, false);

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
        try
        {
            TTransaction::open(self::DB);

            $criteria = new TCriteria;
            $criteria->setProperty('order', 'nome asc');
            $criteria->add(new TFilter('ativo', '=', 'Y'));

            $repo = new TRepository('Servico');
            $rows = $repo->load($criteria, false);

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
            $profissionalId = (int) ($payload['profissional_id'] ?? 0);
            $servicoId = (int) ($payload['servico_id'] ?? 0);

            if ($profissionalId <= 0)
            {
                throw new RuntimeException('Selecione o profissional.');
            }
            if ($servicoId <= 0)
            {
                throw new RuntimeException('Selecione o servico.');
            }

            TTransaction::open(self::DB);

            $criteria = new TCriteria;
            $criteria->add(new TFilter('profissional_id', '=', $profissionalId));
            $criteria->add(new TFilter('servico_id', '=', $servicoId));
            if ($id > 0)
            {
                $criteria->add(new TFilter('id', '!=', $id));
            }

            $repo = new TRepository('ProfissionalServico');
            if ($repo->count($criteria) > 0)
            {
                throw new RuntimeException('Vinculo ja cadastrado.');
            }

            $object = $id > 0 ? new ProfissionalServico($id) : new ProfissionalServico;
            $object->profissional_id = $profissionalId;
            $object->servico_id = $servicoId;
            $object->store();

            $dataRows = self::loadRows(array_merge($param, $payload));

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'rows' => $dataRows['rows'],
                'total' => $dataRows['total'],
                'page' => $dataRows['page'],
                'per_page' => $dataRows['per_page'],
                'message' => 'Vinculo salvo.'
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

            $object = new ProfissionalServico($id);
            $object->delete();

            $dataRows = self::loadRows($param);

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'rows' => $dataRows['rows'],
                'total' => $dataRows['total'],
                'page' => $dataRows['page'],
                'per_page' => $dataRows['per_page'],
                'message' => 'Vinculo removido.'
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

        $repository = new TRepository('ProfissionalServico');
        $total = $repository->count(clone $criteria);

        $criteria = self::buildListCriteria($filters);
        $criteria->setProperty('order', 'id desc');
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
                    'servico' => (string) ($servMap[$row->servico_id] ?? '')
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
                'servico_id' => $filters['servico_id']
            ]
        ];
    }

    private static function resolveListParams(array $param): array
    {
        $profissionalId = (int) ($param['profissional_id_list'] ?? $param['profissional_id'] ?? 0);
        $servicoId = (int) ($param['servico_id_list'] ?? $param['servico_id'] ?? 0);

        $page = max(1, (int) ($param['page'] ?? 1));
        $perPage = (int) ($param['per_page'] ?? 12);
        if ($perPage <= 0)
        {
            $perPage = 12;
        }

        return [
            'profissional_id' => $profissionalId,
            'servico_id' => $servicoId,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    private static function buildListCriteria(array $filters): TCriteria
    {
        $criteria = new TCriteria;

        if (!empty($filters['profissional_id']))
        {
            $criteria->add(new TFilter('profissional_id', '=', $filters['profissional_id']));
        }

        if (!empty($filters['servico_id']))
        {
            $criteria->add(new TFilter('servico_id', '=', $filters['servico_id']));
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
