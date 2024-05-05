<?php

namespace DbTableInstigator;

use DateTime;

class App
{

    const APP_NAME = 'Mike\'s Supertool';
    const APP_VERSION = '0.0.1';
    const HEADER_TEMPLATE = '/**
 * Created by %s v.%s
 * Date: %s
 * Time: %s
 */';

    public static function getTemplateHeader()
    {
        $date = new DateTime;

        return sprintf(
                self::HEADER_TEMPLATE,
                self::APP_NAME,
                self::APP_VERSION,
                $date->format('Y-m-d'),
                $date->format('H:i p')
        );
    }

}
