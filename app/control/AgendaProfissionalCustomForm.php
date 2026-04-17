<?php
class AgendaProfissionalCustomForm extends TPage
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

        $this->html = new THtmlRenderer('app/resources/barbearia/agenda_profissional_custom_form.html');
        $this->html->enableSection('main', [
            'page_title' => _t('Professional agenda'),
            'page_subtitle' => _t('Manage weekly availability.'),
            'label_profissional' => _t('Professional'),
            'label_dia_semana' => _t('Weekday'),
            'label_hora_inicio' => _t('Start time'),
            'label_hora_fim' => _t('End time'),
            'label_intervalo' => _t('Interval (min)'),
            'label_ativo' => _t('Active'),
            'btn_save' => _t('Save'),
            'btn_clear' => _t('Clear')
        ]);

        $panel = new TPanelGroup(_t('Professional agenda'));
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
            $diaSemana = (int) ($payload['dia_semana'] ?? -1);
            $horaInicio = trim((string) ($payload['hora_inicio'] ?? ''));
            $horaFim = trim((string) ($payload['hora_fim'] ?? ''));
            $intervalo = (int) ($payload['intervalo_minutos'] ?? 0);
            $ativo = trim((string) ($payload['ativo'] ?? 'Y'));

            if ($profissionalId <= 0)
            {
                throw new RuntimeException('Selecione o profissional.');
            }
            if ($diaSemana < 0 || $diaSemana > 6)
            {
                throw new RuntimeException('Selecione o dia da semana.');
            }
            if ($horaInicio === '' || $horaFim === '')
            {
                throw new RuntimeException('Informe o horario de inicio e fim.');
            }

            $inicioMin = self::timeToMinutes($horaInicio);
            $fimMin = self::timeToMinutes($horaFim);
            if ($inicioMin <= 0 || $fimMin <= 0 || $inicioMin >= $fimMin)
            {
                throw new RuntimeException('Horario invalido.');
            }

            if ($intervalo < 0)
            {
                $intervalo = 0;
            }

            if ($ativo === '')
            {
                $ativo = 'Y';
            }

            TTransaction::open(self::DB);

            $object = $id > 0 ? new AgendaProfissional($id) : new AgendaProfissional;
            $object->profissional_id = $profissionalId;
            $object->dia_semana = $diaSemana;
            $object->hora_inicio = Agendamento::normalizeTime($horaInicio);
            $object->hora_fim = Agendamento::normalizeTime($horaFim);
            $object->intervalo_minutos = $intervalo;
            $object->ativo = $ativo;
            $object->store();

            $dataRows = self::loadRows(array_merge($param, $payload));

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'rows' => $dataRows['rows'],
                'total' => $dataRows['total'],
                'page' => $dataRows['page'],
                'per_page' => $dataRows['per_page'],
                'message' => 'Agenda salva.'
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

            $object = new AgendaProfissional($id);
            $object->delete();

            $dataRows = self::loadRows($param);

            TTransaction::close();

            self::jsonResponse([
                'success' => true,
                'rows' => $dataRows['rows'],
                'total' => $dataRows['total'],
                'page' => $dataRows['page'],
                'per_page' => $dataRows['per_page'],
                'message' => 'Agenda removida.'
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

        $repository = new TRepository('AgendaProfissional');
        $total = $repository->count(clone $criteria);

        $criteria = self::buildListCriteria($filters);
        $criteria->setProperty('order', 'profissional_id asc, dia_semana asc, hora_inicio asc');
        $criteria->setProperty('limit', $filters['per_page']);
        $criteria->setProperty('offset', ($filters['page'] - 1) * $filters['per_page']);

        $rows = $repository->load($criteria, false);

        $profRepo = new TRepository('Profissional');
        $profList = $profRepo->load(new TCriteria, false);
        $profMap = [];
        if ($profList)
        {
            foreach ($profList as $prof)
            {
                $profMap[$prof->id] = $prof->nome;
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
                    'profissional' => (string) ($profMap[$row->profissional_id] ?? ''),
                    'dia_semana' => (int) $row->dia_semana,
                    'dia_semana_label' => self::getDiaSemanaLabel((int) $row->dia_semana),
                    'hora_inicio' => (string) $row->hora_inicio,
                    'hora_fim' => (string) $row->hora_fim,
                    'intervalo_minutos' => (int) ($row->intervalo_minutos ?? 0),
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
                'profissional_id' => $filters['profissional_id'],
                'dia_semana' => $filters['dia_semana'],
                'ativo' => $filters['ativo']
            ]
        ];
    }

    private static function resolveListParams(array $param): array
    {
        $profissionalId = (int) ($param['profissional_id_list'] ?? $param['profissional_id'] ?? 0);
        $diaSemana = (string) ($param['dia_semana_list'] ?? $param['dia_semana'] ?? '');
        $ativo = trim((string) ($param['ativo_list'] ?? $param['ativo'] ?? ''));

        $page = max(1, (int) ($param['page'] ?? 1));
        $perPage = (int) ($param['per_page'] ?? 12);
        if ($perPage <= 0)
        {
            $perPage = 12;
        }

        return [
            'profissional_id' => $profissionalId,
            'dia_semana' => $diaSemana,
            'ativo' => $ativo,
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

        if ($filters['dia_semana'] !== '')
        {
            $criteria->add(new TFilter('dia_semana', '=', (int) $filters['dia_semana']));
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

    private static function timeToMinutes(string $value): int
    {
        $value = Agendamento::normalizeTime($value);
        if ($value === '')
        {
            return 0;
        }

        $parts = explode(':', $value);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $s = (int) ($parts[2] ?? 0);
        return ($h * 60) + $m + (int) floor($s / 60);
    }

    private static function getDiaSemanaLabel(int $diaSemana): string
    {
        $map = [
            0 => 'Domingo',
            1 => 'Segunda',
            2 => 'Terca',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sabado'
        ];

        return $map[$diaSemana] ?? '-';
    }

    private static function jsonResponse($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
