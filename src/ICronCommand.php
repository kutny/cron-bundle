<?php

namespace Kutny\CronBundle;

use Kutny\DateTimeBundle\Date\Date;
use Kutny\DateTimeBundle\Time\Time;

interface ICronCommand
{
    public function shouldBeRun(Date $cronRunDate, Time $cronRunTime);

    public function getName();
}
