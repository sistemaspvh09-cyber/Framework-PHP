<?php
class AgendarCustomForm extends TPage
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

        $this->html = new THtmlRenderer('app/resources/barbearia/agendar_custom_form.html');
        $this->html->enableSection('main', [
            'page_title' => _t('Schedule'),
            'page_subtitle' => _t('Quick appointment scheduling.'),
            'label_profissional' => _t('Professional'),
            'label_servico' => _t('Service'),
            'label_data' => _t('Date'),
            'label_hora' => _t('Start time'),
            'label_nome' => _t('Name'),
            'label_telefone' => _t('Phone'),
            'label_observacao' => _t('Notes'),
            'btn_save' => _t('Save'),
            'btn_clear' => _t('Clear')
        ]);

        $panel = new TPanelGroup(_t('Schedule'));
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

    public static function onGetHorarios($param)
    {
        try
        {
            $profissionalId = (int) ($param['profissional_id'] ?? 0);
            $servicoId = (int) ($param['servico_id'] ?? 0);
            $data = trim((string) ($param['data'] ?? ''));

            $data = Agendamento::normalizeDate($data);
            if ($profissionalId <= 0 || $servicoId <= 0 || $data === '')
            {
                self::jsonResponse(['slots' => []]);
            }

            TTransaction::open(self::DB);

            $slots = AgendamentoService::listarHorariosDisponiveis($profissionalId, $servicoId, $data, null);

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

            $profissionalId = (int) ($payload['profissional_id'] ?? 0);
            $servicoId = (int) ($payload['servico_id'] ?? 0);
            $data = trim((string) ($payload['data'] ?? ''));
            $data = Agendamento::normalizeDate($data);
            $horaInicio = trim((string) ($payload['hora_inicio'] ?? ''));
            $nomeCliente = trim((string) ($payload['nome_cliente'] ?? ''));
            $telefoneCliente = trim((string) ($payload['telefone_cliente'] ?? ''));
            $observacao = trim((string) ($payload['observacao'] ?? ''));

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
                throw new RuntimeException('Informe o nome.');
            }
            if ($telefoneCliente === '')
            {
                throw new RuntimeException('Informe o telefone.');
            }

            TTransaction::open(self::DB);

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

            if (!AgendamentoService::validarDisponibilidade($profissionalId, $data, $horaInicio, $horaFim, null))
            {
                throw new RuntimeException('Conflito de horario para este profissional.');
            }

            $object = new Agendamento;
            $object->profissional_id = $profissionalId;
            $object->servico_id = $servicoId;
            $object->data_agendamento = $data;
            $object->hora_inicio = Agendamento::normalizeTime($horaInicio);
            $object->hora_fim = Agendamento::normalizeTime($horaFim);
            $object->status = 'Agendado';
            $object->nome_cliente = $nomeCliente;
            $object->telefone_cliente = $telefoneCliente;
            $object->observacao = $observacao;
            $object->valor_cobrado = (float) ($servico->preco ?? 0);
            $object->updated_at = date('Y-m-d H:i:s');
            $object->created_at = date('Y-m-d H:i:s');
            $object->store();

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'message' => 'Agendamento criado.'
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

    private static function timeToMinutes(string $value): int
    {
        $value = Agendamento::normalizeTime($value);
        $parts = explode(':', $value);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $s = (int) ($parts[2] ?? 0);
        return ($h * 60) + $m + (int) floor($s / 60);
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
