<?php

class FlowUtils
{
    public static function checkDate($date, $format)
    {
        $date = DateTime::createFromFormat($format, $date);
        return $date && $date->format($format) == $date;
    }

    public static function formatPrice($price, $maxNumberOfDigits = 19)
    {
        if (strlen($price) > $maxNumberOfDigits) {
            return null;
        }
        
        return (float) number_format($price, 2, ".", "");
    }
}