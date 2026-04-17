<?php
/**
 * AgendamentoService
 *
 * @version    8.4
 * @package    service
 * @subpackage barbearia
 */
class AgendamentoService
{
    public static function listarHorariosDisponiveis(int $profissionalId, int $servicoId, string $data, ?int $ignoreId = null): array
    {
        $data = Agendamento::normalizeDate($data);
        if ($profissionalId <= 0 || $servicoId <= 0 || trim($data) === '')
        {
            return [];
        }

        $servico = new Servico($servicoId);
        $duracao = (int) ($servico->duracao_minutos ?? 0);
        if ($duracao <= 0)
        {
            return [];
        }

        $diaSemana = (int) date('w', strtotime($data));
        $agendaCriteria = new TCriteria;
        $agendaCriteria->add(new TFilter('profissional_id', '=', $profissionalId));
        $agendaCriteria->add(new TFilter('dia_semana', '=', $diaSemana));
        $agendaCriteria->add(new TFilter('ativo', '=', 'Y'));
        $agendaCriteria->setProperty('order', 'hora_inicio asc');

        $agendaRepo = new TRepository('AgendaProfissional');
        $agendas = $agendaRepo->load($agendaCriteria, false);

        if (!$agendas)
        {
            return [];
        }

        $agCriteria = new TCriteria;
        $agCriteria->add(new TFilter('profissional_id', '=', $profissionalId));
        $agCriteria->add(new TFilter('data_agendamento', '=', $data));
        $agRepo = new TRepository('Agendamento');
        $agendamentos = $agRepo->load($agCriteria, false);

        $ocupados = [];
        if ($agendamentos)
        {
            foreach ($agendamentos as $ag)
            {
                if ($ignoreId && (int) $ag->id === (int) $ignoreId)
                {
                    continue;
                }

                $status = strtolower((string) $ag->status);
                if ($status === 'cancelado')
                {
                    continue;
                }

                $ocupados[] = [
                    self::timeToMinutes(Agendamento::normalizeTime($ag->hora_inicio)),
                    self::timeToMinutes(Agendamento::normalizeTime($ag->hora_fim))
                ];
            }
        }

        $slots = [];
        foreach ($agendas as $agenda)
        {
            $inicio = self::timeToMinutes(Agendamento::normalizeTime($agenda->hora_inicio));
            $fim = self::timeToMinutes(Agendamento::normalizeTime($agenda->hora_fim));
            $intervalo = (int) ($agenda->intervalo_minutos ?? 0);
            $passo = $duracao + max(0, $intervalo);
            if ($passo <= 0)
            {
                $passo = $duracao;
            }

            $cursor = $inicio;
            while (($cursor + $duracao) <= $fim)
            {
                $slotInicio = $cursor;
                $slotFim = $cursor + $duracao;

                if (!self::temConflito($ocupados, $slotInicio, $slotFim))
                {
                    $slots[] = [
                        'hora_inicio' => self::minutesToTime($slotInicio),
                        'hora_fim' => self::minutesToTime($slotFim)
                    ];
                }

                $cursor += $passo;
            }
        }

        return $slots;
    }

    public static function validarDisponibilidade(int $profissionalId, string $data, string $horaInicio, string $horaFim, ?int $ignoreId = null): bool
    {
        $data = Agendamento::normalizeDate($data);
        if ($data === '')
        {
            return false;
        }
        return !Agendamento::hasConflito($profissionalId, $data, $horaInicio, $horaFim, $ignoreId);
    }

    public static function getServicosByProfissional(int $profissionalId, bool $ativosOnly = true): array
    {
        $criteria = new TCriteria;
        $criteria->add(new TFilter('profissional_id', '=', $profissionalId));
        $repo = new TRepository('ProfissionalServico');
        $vinculos = $repo->load($criteria, false);

        if (!$vinculos)
        {
            return [];
        }

        $ids = [];
        foreach ($vinculos as $vinculo)
        {
            $ids[] = (int) $vinculo->servico_id;
        }

        if (!$ids)
        {
            return [];
        }

        $servicoCriteria = new TCriteria;
        $servicoCriteria->add(new TFilter('id', 'in', $ids));
        if ($ativosOnly)
        {
            $servicoCriteria->add(new TFilter('ativo', '=', 'Y'));
        }
        $servicoCriteria->setProperty('order', 'nome asc');

        $servicoRepo = new TRepository('Servico');
        return $servicoRepo->load($servicoCriteria, false) ?: [];
    }

    private static function timeToMinutes(string $value): int
    {
        $value = Agendamento::normalizeTime($value);
        if ($value === '')
        {
            return 0;
        }

        $partes = explode(':', $value);
        $h = (int) ($partes[0] ?? 0);
        $m = (int) ($partes[1] ?? 0);
        $s = (int) ($partes[2] ?? 0);
        return ($h * 60) + $m + (int) floor($s / 60);
    }

    private static function minutesToTime(int $minutes): string
    {
        $h = floor($minutes / 60) % 24;
        $m = $minutes % 60;
        return sprintf('%02d:%02d:00', $h, $m);
    }

    private static function temConflito(array $ocupados, int $inicio, int $fim): bool
    {
        foreach ($ocupados as $ocupado)
        {
            $ini = (int) ($ocupado[0] ?? 0);
            $fimOcupado = (int) ($ocupado[1] ?? 0);
            if ($inicio < $fimOcupado && $fim > $ini)
            {
                return true;
            }
        }

        return false;
    }
}
