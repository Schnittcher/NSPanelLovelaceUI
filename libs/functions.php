<?php

declare(strict_types=1);

trait Functions
{
    protected function Scale($Value, $SourceMinValue, $SourceMaxValue, $DestMinValue, $DestMaxValue)
    {
        return ($Value - $SourceMinValue) / ($SourceMaxValue - $SourceMinValue) * ($DestMaxValue - $DestMinValue) + $DestMinValue;
    }

    protected function HSVtoRGB($H, $S, $V)
    {
        $H *= 6;
        $I = floor($H);
        $F = $H - $I;
        $M = $V * (1 - $S);
        $N = $V * (1 - $S * $F);
        $K = $V * (1 - $S * (1 - $F));
        switch ($I) {
            case 0:
                list($R, $G, $B) = [$V, $K, $M];
                break;
            case 1:
                list($R, $G, $B) = [$N, $V, $M];
                break;
            case 2:
                list($R, $G, $B) = [$M, $V, $K];
                break;
            case 3:
                list($R, $G, $B) = [$M, $N, $V];
                break;
            case 4:
                list($R, $G, $B) = [$K, $M, $V];
                break;
            case 5:
            case 6: //for when $H=1 is given
                list($R, $G, $B) = [$V, $M, $N];
                break;
        }
        return [intval($R * 255), intval($G * 255), intval($B * 255)];
    }

    protected function pos_to_color($x, $y, $wh)
    {
        $r = $wh / 2;
        $x = (($x - $r) / $r * 100) / 100;
        $y = (($r - $y) / $r * 100) / 100;

        $r = sqrt($x * $x + $y * $y);
        $sat = 0;
        if ($r > 1) {
            $sat = 0;
        } else {
            $sat = $r;
        }
        $h = rad2deg(atan2($y, $x));
        $h = (360 + ($h % 360)) % 360;
        $h = $h / 360;
        return $this->HSVtoRGB($h, $sat, 1);
    }
}