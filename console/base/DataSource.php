<?php

namespace alexvendor2018\fias\console\base;

interface DataSource
{
    public function getRows($maxCount = 1000);
}
