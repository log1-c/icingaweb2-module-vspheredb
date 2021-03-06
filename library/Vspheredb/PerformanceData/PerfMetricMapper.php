<?php

namespace Icinga\Module\Vspheredb\PerformanceData;

use Icinga\Module\Vspheredb\MappedClass\PerfEntityMetricCSV;
use Icinga\Module\Vspheredb\MappedClass\PerfMetricId;
use Icinga\Module\Vspheredb\PerformanceData\InfluxDb\DataPoint;
use Icinga\Module\Vspheredb\Util;
use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;

class PerfMetricMapper
{
    protected $vCenter;

    protected $counters;

    protected $prefix;

    public function __construct($counters)
    {
        $this->counters = $counters;
    }

    public function process(PerfEntityMetricCSV $metric)
    {
        $object = $metric->entity;
        $dates = $this->parseDates($metric);
        $result = [];
        foreach ($metric->value as $series) {
            $key = $this->makeKey($object, $series->id);
            $metric = $this->getCounterIdName($series->id->counterId);
            foreach (array_combine(
                $dates,
                preg_split('/,/', $series->value)
            ) as $time => $value) {
                $result[$time][$key][$metric] = $value;
            }
        }

        return $result;
    }

    /**
     * @param PerfEntityMetricCSV $metric
     * @return DataPoint[]
     */
    public function makeInfluxDataPoints(PerfEntityMetricCSV $metric, $measurement, $tags)
    {
        $points = [];
        foreach ($this->process($metric) as $ts => $values) {
            foreach ($values as $file => $metrics) {
                $points[] = new DataPoint(
                    $measurement,
                    $tags[$file],
                    $metrics,
                    $ts * 1000000
                );
            }
        }

        return $points;
    }

    protected function makeKey(ManagedObjectReference $ref, PerfMetricId $id)
    {
        $ref = $ref->_;
        if (strlen($id->instance)) {
            return "$ref/" . $id->instance;
        } else {
            return $ref;
        }
    }

    protected function getCounterIdName($id)
    {

        return $this->counters[$id];
    }

    protected function parseDates(PerfEntityMetricCSV $metric)
    {
        $parts = preg_split('/,/', $metric->sampleInfoCSV);
        $max = count($parts) - 1;
        $dates = [];
        for ($i = 1; $i <= $max; $i += 2) {
            $dates[] = Util::timeStringToUnixMs($parts[$i]);
        }

        return $dates;
    }
}
