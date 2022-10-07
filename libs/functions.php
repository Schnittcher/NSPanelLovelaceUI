<?php

declare(strict_types=1);

trait Functions
{
    protected function Scale($Value, $SourceMinValue, $SourceMaxValue, $DestMinV0alue, $DestMaxValue)
    {
        return ($Value - $SourceMinValue) / ($SourceMaxValue - $SourceMinValue) * ($DestMaxValue - $DestMinValue) + $DestMinValue;
    }
}