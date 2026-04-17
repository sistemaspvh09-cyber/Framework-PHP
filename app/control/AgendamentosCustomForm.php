<?php
class AgendamentosCustomForm extends TPage
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

        $this->html = new THtmlRenderer('app/resources/barbearia/agendamentos_custom_form.html');
        $this->html->enableSection('main', [
            'page_title' => _t('Appointments'),
            'page_subtitle' => _t('Manage barbershop appointments.'),
            'label_profissional' => _t('Professional'),
            'label_servico' => _t('Service'),
            'label_data' => _t('Date'),
            'label_hora' => _t('Start time'),
            'label_status' => _t('Status'),
            'label_cliente' => _t('Client'),
            'label_telefone' => _t('Phone'),
            'label_valor' => _t('Amount'),
            'label_pagamento' => _t('Payment method'),
            'label_observacao' => _t('Notes'),
            'btn_save' => _t('Save'),
            'btn_clear' => _t('Clear')
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
        try
        {
            $profissionalId = (int) ($param['profissional_id'] ?? 0);

            TTransaction::open(self::DB);

            if ($profissionalId > 0)
            {
                $servicos = AgendamentoService::getServicosByProfissional($profissionalId, true);
            }
            else
            {
                $criteria = new TCriteria;
                $criteria->add(new TFilter('ativo', '=', 'Y'));
                $criteria->setProperty('order', 'nome asc');
                $repo = new TRepository('Servico');
                $servicos = $repo->load($criteria, false) ?: [];
            }

            $data = [];
            foreach ($servicos as $servico)
            {
                $data[] = [
                    'id' => (int) $servico->id,
                    'nome' => (string) $servico->nome,
                    'preco' => (float) ($servico->preco ?? 0),
                    'duracao_minutos' => (int) ($servico->duracao_minutos ?? 0)
                ];
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

    public static function onGetHorarios($param)
    {
        try
        {
            $profissionalId = (int) ($param['profissional_id'] ?? 0);
            $servicoId = (int) ($param['servico_id'] ?? 0);
            $data = trim((string) ($param['data'] ?? ''));
            $ignoreId = (int) ($param['ignore_id'] ?? 0);

            $data = Agendamento::normalizeDate($data);
            if ($profissionalId <= 0 || $servicoId <= 0 || $data === '')
            {
                self::jsonResponse(['slots' => []]);
            }

            TTransaction::open(self::DB);

            $slots = AgendamentoService::listarHorariosDisponiveis($profissionalId, $servicoId, $data, $ignoreId > 0 ? $ignoreId : null);

            TTransaction::close();

            self::jsonResponse(['slots' => $slots]);
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
            $data = trim((string) ($payload['data'] ?? ''));
            $data = Agendamento::normalizeDate($data);
            $horaInicio = trim((string) ($payload['hora_inicio'] ?? ''));
            $status = trim((string) ($payload['status'] ?? 'Agendado'));
            $nomeCliente = trim((string) ($payload['nome_cliente'] ?? ''));
            $telefoneCliente = trim((string) ($payload['telefone_cliente'] ?? ''));
            $observacao = trim((string) ($payload['observacao'] ?? ''));
            $valorCobrado = (string) ($payload['valor_cobrado'] ?? '');
            $formaPagamento = trim((string) ($payload['forma_pagamento'] ?? ''));

            if ($profissionalId <= 0)
            {
                throw new RuntimeException('Selecione o profissional.');
            }
            if ($servicoId <= 0)
            {
                throw new RuntimeException('Selecione o servico.');
            }
            if ($data === '')
            {
                throw new RuntimeException('Informe a data.');
            }
            if ($horaInicio === '')
            {
                throw new RuntimeException('Informe o horario.');
            }
            if ($nomeCliente === '')
            {
                throw new RuntimeException('Informe o nome do cliente.');
            }

            TTransaction::open(self::DB);

            if ($id > 0)
            {
                $existente = new Agendamento($id);
                if (strtolower((string) $existente->status) === 'concluido')
                {
                    throw new RuntimeException('Agendamento concluido nao pode ser editado.');
                }
            }

            $servico = new Servico($servicoId);
            $duracao = (int) ($servico->duracao_minutos ?? 0);
            if ($duracao <= 0)
            {
                throw new RuntimeException('Duracao do servico invalida.');
            }

            $horaFim = Agendamento::calcularHoraFim($horaInicio, $duracao);

            if (!self::horarioDentroAgenda($profissionalId, $data, $horaInicio, $horaFim))
            {
                throw new RuntimeException('Horario fora da agenda do profissional.');
            }

            if (!AgendamentoService::validarDisponibilidade($profissionalId, $data, $horaInicio, $horaFim, $id > 0 ? $id : null))
            {
                throw new RuntimeException('Conflito de horario para este profissional.');
            }

            $object = $id > 0 ? new Agendamento($id) : new Agendamento;
            $object->profissional_id = $profissionalId;
            $object->servico_id = $servicoId;
            $object->data_agendamento = $data;
            $object->hora_inicio = Agendamento::normalizeTime($horaInicio);
            $object->hora_fim = Agendamento::normalizeTime($horaFim);
            $object->status = $status !== '' ? $status : 'Agendado';
            $object->nome_cliente = $nomeCliente;
            $object->telefone_cliente = $telefoneCliente;
            $object->observacao = $observacao;
            $object->valor_cobrado = ($valorCobrado !== '') ? (float) $valorCobrado : (float) ($servico->preco ?? 0);
            $object->forma_pagamento = $formaPagamento;
            $object->updated_at = date('Y-m-d H:i:s');
            if ($id <= 0)
            {
                $object->created_at = date('Y-m-d H:i:s');
            }
            $object->store();

            if (strtolower($object->status) === 'concluido')
            {
                self::gerarMovimentoCaixa($object);
            }

            $dataRows = self::loadRows(array_merge($param, $payload));

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'rows' => $dataRows['rows'],
                'total' => $dataRows['total'],
                'page' => $dataRows['page'],
                'per_page' => $dataRows['per_page'],
                'message' => 'Agendamento salvo.'
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

            $object = new Agendamento($id);
            $object->delete();

            $dataRows = self::loadRows($param);

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'rows' => $dataRows['rows'],
                'total' => $dataRows['total'],
                'page' => $dataRows['page'],
                'per_page' => $dataRows['per_page'],
                'message' => 'Agendamento removido.'
            ]);
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            self::jsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public static function onCancel($param)
    {
        try
        {
            $id = (int) ($param['id'] ?? 0);
            if ($id <= 0)
            {
                throw new RuntimeException('Registro invalido.');
            }

            TTransaction::open(self::DB);

            $agendamento = new Agendamento($id);
            if (strtolower((string) $agendamento->status) === 'concluido')
            {
                throw new RuntimeException('Agendamento concluido nao pode ser cancelado.');
            }

            $agendamento->status = 'Cancelado';
            $agendamento->forma_pagamento = null;
            $agendamento->data_conclusao = null;
            $agendamento->updated_at = date('Y-m-d H:i:s');
            $agendamento->store();

            $dataRows = self::loadRows($param);

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'rows' => $dataRows['rows'],
                'total' => $dataRows['total'],
                'page' => $dataRows['page'],
                'per_page' => $dataRows['per_page'],
                'message' => 'Agendamento cancelado.'
            ]);
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            self::jsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    public static function onConcluir($param)
    {
        try
        {
            $payload = self::getPayload($param);

            $id = (int) ($payload['id'] ?? 0);
            $forma = trim((string) ($payload['forma_pagamento'] ?? ''));

            if ($id <= 0)
            {
                throw new RuntimeException('Registro invalido.');
            }

            $formasValidas = ['Pix', 'Dinheiro', 'Cartao'];
            if (!in_array($forma, $formasValidas, true))
            {
                throw new RuntimeException('Forma de pagamento invalida.');
            }

            TTransaction::open(self::DB);

            $agendamento = new Agendamento($id);
            $statusAtual = strtolower((string) $agendamento->status);
            $permitidos = ['agendado', 'confirmado', 'em atendimento'];
            if (!in_array($statusAtual, $permitidos, true))
            {
                throw new RuntimeException('Status nao permite concluir.');
            }

            $agendamento->status = 'Concluido';
            $agendamento->forma_pagamento = $forma;
            $agendamento->data_conclusao = date('Y-m-d H:i:s');
            $agendamento->updated_at = date('Y-m-d H:i:s');
            $agendamento->store();

            self::gerarMovimentoCaixa($agendamento);

            $dataRows = self::loadRows(array_merge($param, $payload));

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'rows' => $dataRows['rows'],
                'total' => $dataRows['total'],
                'page' => $dataRows['page'],
                'per_page' => $dataRows['per_page'],
                'message' => 'Agendamento concluido.'
            ]);
        }
        catch (Exception $e)
        {
            TTransaction::rollback();
            self::jsonResponse(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    private static function horarioDentroAgenda(int $profissionalId, string $data, string $horaInicio, string $horaFim): bool
    {
        $diaSemana = (int) date('w', strtotime($data));
        $criteria = new TCriteria;
        $criteria->add(new TFilter('profissional_id', '=', $profissionalId));
        $criteria->add(new TFilter('dia_semana', '=', $diaSemana));
        $criteria->add(new TFilter('ativo', '=', 'Y'));

        $repo = new TRepository('AgendaProfissional');
        $agendas = $repo->load($criteria, false);

        if (!$agendas)
        {
            return false;
        }

        $inicio = self::timeToMinutes(Agendamento::normalizeTime($horaInicio));
        $fim = self::timeToMinutes(Agendamento::normalizeTime($horaFim));

        foreach ($agendas as $agenda)
        {
            $iniAgenda = self::timeToMinutes(Agendamento::normalizeTime($agenda->hora_inicio));
            $fimAgenda = self::timeToMinutes(Agendamento::normalizeTime($agenda->hora_fim));
            if ($inicio >= $iniAgenda && $fim <= $fimAgenda)
            {
                return true;
            }
        }

        return false;
    }

    private static function gerarMovimentoCaixa(Agendamento $agendamento): void
    {
        $criteria = new TCriteria;
        $criteria->add(new TFilter('agendamento_id', '=', $agendamento->id));
        $repo = new TRepository('MovimentoCaixa');
        if ($repo->count($criteria) > 0)
        {
            return;
        }

        $mov = new MovimentoCaixa;
        $dataMovimento = $agendamento->data_conclusao ?: $agendamento->data_agendamento;
        $dataMovimento = Agendamento::normalizeDate((string) $dataMovimento);
        $mov->data_movimento = $dataMovimento;
        $mov->tipo = 'E';
        $mov->descricao = 'Agendamento #' . $agendamento->id;
        $mov->valor = (float) ($agendamento->valor_cobrado ?? 0);
        $mov->categoria = 'Agendamento';
        $mov->agendamento_id = $agendamento->id;
        $mov->created_at = date('Y-m-d H:i:s');
        $mov->store();
    }

    private static function timeToMinutes(string $value): int
    {
        $value = Agendamento::normalizeTime($value);
        $parts = explode(':', $value);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $s = (int) ($parts[2] ?? 0);
        return ($h * 60) + $m + (int) floor($s / 60);
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
        $data = trim((string) ($param['data_list'] ?? $param['data'] ?? ''));
        $data = Agendamento::normalizeDate($data);
        if ($data === '')
        {
            $data = date('Y-m-d');
        }

        $page = max(1, (int) ($param['page'] ?? 1));
        $perPage = (int) ($param['per_page'] ?? 12);
        if ($perPage <= 0)
        {
            $perPage = 12;
        }

        return [
            'profissional_id' => $profissionalId,
            'data' => $data,
            'page' => $page,
            'per_page' => $perPage
        ];
    }

    private static function buildListCriteria(array $filters): TCriteria
    {
        $criteria = new TCriteria;
        $criteria->add(new TFilter('data_agendamento', '=', $filters['data']));
        if (!empty($filters['profissional_id']))
        {
            $criteria->add(new TFilter('profissional_id', '=', $filters['profissional_id']));
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
