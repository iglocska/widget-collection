<?php

class CsseCovidWidget
{
    public $title = 'CSSE Covid-19 data';
    public $render = 'BarChart';
    public $width = 3;
    public $height = 4;
    public $params = array(
        'event_info' => 'Substring included in the info field of relevant CSSE COVID-19 events.',
        'type' => 'Type of data used for the widget (confirmed, death, recovered).',
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
        if (!empty($eventIds)) {
            $events = $this->Event->fetchEvent($user, $params);
            if (!empty($events)) {
                foreach ($events as $event) {
                    if (!empty($event['Object'])) {
                        foreach ($event['Object'] as $object) {
                            $temp = array();
                            if ($object['name'] === 'covid19-csse-daily-report') {
                                foreach ($object['Attribute'] as $attribute) {
                                    if ($attribute['object_relation'] === 'country-region') {
                                        $temp['country-region'] = $attribute['value'];
                                    } else if ($attribute['object_relation'] === 'confirmed') {
                                        $temp['confirmed'] = $attribute['value'];
                                    } else if ($attribute['object_relation'] === 'death') {
                                        $temp['death'] = $attribute['value'];
                                    } else if ($attribute['object_relation'] === 'recovered') {
                                        $temp['recovered'] = $attribute['value'];
                                    }
                                }
                                if (empty($data[$temp['country-region']])) {
                                    if (!empty($temp[empty($options['type']) ? 'confirmed' : $options['type']])) {
                                        $data[$temp['country-region']] = $temp[empty($options['type']) ? 'confirmed' : $options['type']];
                                    }
                                } else {
                                    if (!empty($temp[empty($options['type']) ? 'confirmed' : $options['type']])) {
                                        $data[$temp['country-region']] += $temp[empty($options['type']) ? 'confirmed' : $options['type']];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            arsort($data);
        }
        $data = array('data' => $data);
        if (!empty($options['logarithmic'])) {
            $data['logarithmic'] = array();
            foreach ($data['data'] as $k => $v) {
                $data['logarithmic'][$k] = log10($v);
            }
        }
        return $data;
	}
}
