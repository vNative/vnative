<?php

/**
 * Scheduler Class which executes daily and perfoms the initiated job
 * 
 * @author Faizan Ayubi
 */

class CRON extends Auth {

    public function __construct($options = array()) {
        parent::__construct($options);
        $this->willRenderLayoutView = false;
        $this->willRenderActionView = false;
    }

    public function index() {
        $this->verify();
    }
    
    protected function verify() {
        $startdate = date('Y-m-d', strtotime("-10 day"));
        $enddate = date('Y-m-d', strtotime("now"));
        $where = array(
            "live = ?" => true,
            "created >= ?" => $startdate,
            "created <= ?" => $enddate
        );
        $links = Link::all($where, array("id", "short", "item_id", "user_id"));
        $total = Link::count($where);

        $counter = 0;
        $googl = Framework\Registry::get("googl");
        foreach ($links as $link) {
            $object = $googl->analyticsFull($link->short);
            $count = $object->analytics->day->shortUrlClicks;
            //minimum count for earning
            if ($count > 15) {
                $stat = $this->saveStats($object, $link, $count);
                $this->saveEarnings($link, $count, $stat, $object);

                //sleep the script
                if ($counter == 100) {
                    sleep(3);
                    $counter = 0;
                }
                ++$counter;
            }
        }
    }

    protected function saveStats($object, $link, $count) {
        $stat = new Stat(array(
            "user_id" => $link->user_id,
            "link_id" => $link->id,
            "verifiedClicks" => $count,
            "shortUrlClicks" => $object->analytics->day->shortUrlClicks,
            "longUrlClicks" => $object->analytics->day->longUrlClicks,
            "referrers" => json_encode($object->analytics->day->referrers),
            "countries" => json_encode($object->analytics->day->countries),
            "browsers" => json_encode($object->analytics->day->browsers),
            "platforms" => json_encode($object->analytics->day->platforms)
        ));
        $stat->save();
        return $stat;
    }
    
    protected function saveEarnings($link, $count, $stat, $object) {
        $revenue = 0;$country_count = 0;$nonverified_count = 0;$verified_count = 0;

        $referrers = $object->analytics->day->referrers;
        foreach ($referrers as $referer) {
            if ($referer->id == 'chocoghar.com') {
                $nonverified_count += $referer->count;
            }
        }
        $verified_count = $count - $nonverified_count;
        $correct = 1;

        $countries = $object->analytics->day->countries;

        $rpms = RPM::all(array("item_id = ?" => $link->item_id), array("value", "country"));
        $rpms_country = array();
        $rpms_value = array();

        foreach ($rpms as $rpm) {
            $rpms_country[] = strtoupper($rpm->country);
            $rpms_value[strtoupper($rpm->country)] = $rpm->value;
        }
        foreach ($countries as $country) {
            if (in_array($country->id, $rpms_country)) {
                $revenue += $correct*($rpms_value[$country->id])*($country->count)/1000;
                $country_count += $country->count;
            }
        }
        if ($verified_count > $country_count) {
            $revenue += ($verified_count - $country_count)*$correct*($rpms_value["NONE"])/1000;
        }

        $avgrpm = round(($revenue*1000)/($count), 2);
        $earning = new Earning(array(
            "item_id" => $link->item_id,
            "link_id" => $link->id,
            "amount" => $revenue,
            "user_id" => $link->user_id,
            "stat_id" => $stat->id,
            "rpm" => $avgrpm,
            "live" => 1
        ));
        $earning->save();
    }
}
