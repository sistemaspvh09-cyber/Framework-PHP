<?php
/**
 * Agendamento
 *
 * @version    8.4
 * @package    model
 * @subpackage barbearia
 */
class Agendamento extends TRecord
{
    const TABLENAME  = 'agendamento';
    const PRIMARYKEY = 'id';
    const IDPOLICY   = 'max'; // {max, serial}

    /**
     * Constructor method
     */
    public function __construct($id = NULL, $callObjectLoad = TRUE)
    {
        parent::__construct($id, $callObjectLoad);
        parent::addAttribute('profissional_id');
        parent::addAttribute('servico_id');
        parent::addAttribute('data_agendamento');
        parent::addAttribute('hora_inicio');
        parent::addAttribute('hora_fim');
        parent::addAttribute('status');
        parent::addAttribute('nome_cliente');
        parent::addAttribute('telefone_cliente');
        parent::addAttribute('observacao');
        parent::addAttribute('valor_cobrado');
        parent::addAttribute('forma_pagamento');
        parent::addAttribute('created_at');
        parent::addAttribute('updated_at');
        parent::addAttribute('data_conclusao');
    }

    /**
     * Return profissional
     */
    public function get_profissional()
    {
        return Profissional::find($this->profissional_id);
    }

    /**
     * Return servico
     */
    public function get_servico()
    {
        return Servico::find($this->servico_id);
    }

    public static function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '')
        {
            return '';
        }

        if (strpos($value, '/') !== false)
        {
            $parts = explode('/', $value);
            if (count($parts) === 3)
            {
                $day = (int) ($parts[0] ?? 0);
                $month = (int) ($parts[1] ?? 0);
                $year = (int) ($parts[2] ?? 0);
                if ($year > 0 && $month > 0 && $day > 0)
                {
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
        }

        if (strpos($value, '-') !== false)
        {
            $parts = explode('-', $value);
            if (count($parts) === 3)
            {
                $first = (string) ($parts[0] ?? '');
                if (strlen($first) === 4)
                {
                    return $value;
                }

                $day = (int) ($parts[0] ?? 0);
                $month = (int) ($parts[1] ?? 0);
                $year = (int) ($parts[2] ?? 0);
                if ($year > 0 && $month > 0 && $day > 0)
                {
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
        }

        $time = strtotime($value);
        if ($time === false)
        {
            return '';
        }

        return date('Y-m-d', $time);
    }

    public static function normalizeTime(string $value): string
    {
        $value = trim($value);
        if ($value === '')
        {
            return '';
        }

        if (strpos($value, ':') === false)
        {
            return '';
        }

        $parts = explode(':', $value);
        $h = (int) ($parts[0] ?? 0);
        $m = (int) ($parts[1] ?? 0);
        $s = (int) ($parts[2] ?? 0);

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    public static function calcularHoraFim(string $horaInicio, int $duracaoMinutos): string
    {
        $inicio = self::timeToMinutes(self::normalizeTime($horaInicio));
        if ($inicio <= 0 || $duracaoMinutos <= 0)
        {
            return '';
        }

        $fim = $inicio + $duracaoMinutos;
        return self::minutesToTime($fim);
    }

    public static function hasConflito(int $profissionalId, string $data, string $horaInicio, string $horaFim, ?int $ignoreId = null): bool
    {
        $data = self::normalizeDate($data);
        if ($profissionalId <= 0 || $data === '')
        {
            return true;
        }

        $criteria = new TCriteria;
        $criteria->add(new TFilter('profissional_id', '=', $profissionalId));
        $criteria->add(new TFilter('data_agendamento', '=', $data));

        $repo = new TRepository('Agendamento');
        $rows = $repo->load($criteria, false);

        if (!$rows)
        {
            return false;
        }

        $inicio = self::timeToMinutes(self::normalizeTime($horaInicio));
        $fim = self::timeToMinutes(self::normalizeTime($horaFim));

        foreach ($rows as $row)
        {
            if ($ignoreId && (int) $row->id === (int) $ignoreId)
            {
                continue;
            }

            $status = strtolower((string) $row->status);
            if ($status === 'cancelado')
            {
                continue;
            }

            $iniAg = self::timeToMinutes(self::normalizeTime($row->hora_inicio));
            $fimAg = self::timeToMinutes(self::normalizeTime($row->hora_fim));
            if ($inicio < $fimAg && $fim > $iniAg)
            {
                return true;
            }
        }

        return false;
    }

    private static function timeToMinutes(string $value): int
    {
        $value = self::normalizeTime($value);
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

    private static function minutesToTime(int $minutes): string
    {
        $h = floor($minutes / 60) % 24;
        $m = $minutes % 60;
        return sprintf('%02d:%02d:00', $h, $m);
    }
}
