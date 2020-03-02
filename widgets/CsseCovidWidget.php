<?php

class CsseCovidWidget
{
    public $title = 'CSSE Covid-19 data';
    public $render = 'BarChart';
    public $width = 3;
    public $height = 4;
    public $params = array(
        'event_info' => 'Substring included in the info field of relevant CSSE COVID-19 events.',
        'type' => 'Type of data used for the widget (confirmed, death, recovered, mortality).',
        'logarithmic' => 'Use a log10 scale for the graph (set via 0/1).'
    );
    public $description = 'Widget visualising the countries ranked by highest count in the chosen category.';
    public $placeholder =
'{
    "event_info": "%CSSE COVID-19 daily report%",
    "type": "confirmed",
    "logarithmic": 1
}';

    public function handler($user, $options = array())
    {
        $this->Event = ClassRegistry::init('Event');
        $event_info_condition = empty($options['event_info']) ? '%CSSE COVID-19 daily report%' : $options['event_info'];
        $params = array(
            'eventinfo' => $event_info_condition,
            'order' => 'date desc',
            'limit' => 1,
            'page' => 1
        );
        $eventIds = $this->Event->filterEventIds($user, $params);
        $params['eventid'] = $eventIds;
        $data = array();
        if (empty($options['type'])) {
            $options['type'] = 'confirmed';
        }
        if (!empty($eventIds)) {
            $events = $this->Event->fetchEvent($user, $params);
            $data = $this->__handleEvents($events, $options);
            arsort($data);
        }
        $data = array('data' => $data);
        if (!empty($options['type']) && $options['type'] === 'mortality') {
            $data['output_decorator'] = 'percentage';
        }
        if (!empty($options['logarithmic'])) {
            $data['logarithmic'] = array();
            foreach ($data['data'] as $k => $v) {
                $data['logarithmic'][$k] = ($v == 0 ? 0 : log10($v));
            }
        }
        return $data;
    }

    private function __handleEvents($events, $options)
    {
        $data = array();
        if (!empty($events)) {
            foreach ($events as $event) {
                if (!empty($event['Object'])) {
                    $data = $this->__handleObjects($data, $event['Object'], $options);
                }
            }
        }
        return $data;
    }

    private function __handleObjects($data, $objects, $options)
    {
        foreach ($objects as $object) {
            if ($object['name'] === 'covid19-csse-daily-report') {
                $temp = $this->__interpretObject($object);
                $data = $this->__rearrangeResults($data, $temp, $options);
            }
        }
        if ($options['type'] === 'mortality') {
            foreach ($data as $k => $v) {
                $data[$k] = round(100 * (empty($v['death']) ? 0 : $v['death']) / $v['confirmed'], 2);
            }
        }
        return $data;
    }

    private function __rearrangeResults($data, $temp, $options)
    {
        $country = $temp['country-region'];
        if ($options['type'] === 'mortality') {
            foreach (array('confirmed', 'death') as $type) {
                if (!empty($temp[$type])) {
                    $data[$country][$type] = (empty($data[$country][$type]) ? $temp[$type] : ($data[$country][$type] + $temp[$type]));
                }
            }
        } else {
            $type = $options['type'];
            if (!empty($temp[$type])) {
                $data[$country] = (empty($data[$country]) ? $temp[$type] : ($data[$country] + $temp[$type]));
            }
        }
        return $data;
    }

    private function __interpretObject($object)
    {
        $temp = array();
        $validFields = array('country-region', 'confirmed', 'death', 'recovered');
        foreach ($object['Attribute'] as $attribute) {
            if (in_array($attribute['object_relation'], $validFields)) {
                if ($attribute['object_relation'] !== 'country-region') {
                    $attribute['value'] = intval($attribute['value']);
                }
                $temp[$attribute['object_relation']] = $attribute['value'];
            }
        }
        return $temp;
    }
}
