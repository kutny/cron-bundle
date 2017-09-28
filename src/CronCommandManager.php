<?php

namespace Kutny\CronBundle;

class CronCommandManager
{
    private $cronCommandServices;

    /**
     * @param array $cronCommandServices
     */
    public function setCronCommandServices(array $cronCommandServices)
    {
        $this->cronCommandServices = $cronCommandServices;
    }

    public function getCronCommandServices()
    {
        return $this->cronCommandServices;
    }
}
