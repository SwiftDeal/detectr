<?php

/**
 * Ping controller
 *
 * @author Shreyansh Goel, Hemant Mann
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;
use Framework\ArrayMethods as ArrayMethods;

class Ping extends Admin {

    /**
     * @before _secure, memberLayout
     */
    public function create() {
        $this->seo(array("title" => "Ping | Create","view" => $this->getLayoutView()));
        $view = $this->getActionView();

        if (RequestMethods::post('title')) {
            $ping = Registry::get('MongoDB')->ping;
            $time = strtotime(date('d-m-Y H:i:s'));
            $mongo_date = new MongoDate($time);

            $url = RequestMethods::post('url', '');
            $regex = Shared\Markup::websiteRegex();
            if (!preg_match("/^$regex$/", $url)) {
                $view->set("success", "Invalid Url");
                return;
            }

            $record = $ping->findOne(array('user_id' => (int) $this->user->id, 'url' => $url));
            if ($record) {
                $view->set("success", "Ping already created! Go to <a href='/ping/edit/".$record['record_id']."'>Edit</a>");
                return;
            }

            $count = $ping->count();
            $ping->insert(array(
                "user_id" => (int) $this->user->id,
                "record_id" => $count + 1,
                "title" => RequestMethods::post('title'),
                "url" => $url,
                "interval" => RequestMethods::post('interval'),
                "live" => 1,
                "created" => $mongo_date,
            ));

            $view->set('success', 'Ping Created Successfully');
        }
    }
	
    /**
     * @before _secure, memberLayout
     */
    public function edit($id) {
        $this->seo(array("title" => "Ping | Edit","view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $ping = Registry::get('MongoDB')->ping;
        $record = $ping->findOne(array('record_id' => (int) $id));
        if (!$record) {
            self::redirect('/member/index');
        }

        if (RequestMethods::post('title')) {
            $time = strtotime(date('d-m-Y H:i:s'));
            $mongo_date = new MongoDate($time);

            $ping->update(array("record_id"=> (int) $id), array(
                '$set' => array(
                    "title" => RequestMethods::post('title'),
                    "interval" => RequestMethods::post('interval'),
                    "modified" => $mongo_date,
                )
            ));
            $record = $ping->findOne(array('record_id' => (int) $id));
            $view->set("success", "Updated!!");
        }
        $view->set('title', $record['title'])
            ->set('url', $record['url'])
            ->set('interval', $record['interval']);
    }

    /**
     * @before _secure, memberLayout
     */
    public function manage() {
        $this->seo(array("title" => "Ping | Manage","view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $mongo = Registry::get('MongoDB');
        $ping = $mongo->ping;

        $page = RequestMethods::get("page", 1);
        $limit = RequestMethods::get("limit", 30);
        $where = array('live' => 1, 'user_id' => (int) $this->user->id);
        $count = $ping->count($where);
        
        $records = $ping->find($where);
        $records->skip($limit * ($page - 1));
        $records->limit($limit);
        $records->sort(array('created' => -1));
        
        $result = array();
        foreach ($records as $r) {
            $result[] = $r;
        }

        $view->set('records', $result)
            ->set('page', $page)
            ->set('limit', $limit)
            ->set('count', $count);
    }
/*
    public function hits(){
        $url, $user_id
        $view->set('c')
    }
*/
    /**
     * @before _secure, memberLayout
     */
    public function remove($id){
        $mongo = Registry::get('MongoDB');

        $ping = $mongo->ping;
        $ping->update(array("record_id" => (int) $id), array(
            '$set' => array("live" => 0)
        ));

        self::redirect('/ping/manage');
    }

    /**
     * @before _secure, memberLayout
     */
    public function stats($record_id) {
        $this->seo(array("title" => "Ping | Stats","view" => $this->getLayoutView()));
        $view = $this->getActionView();

        $ping = Registry::get("MongoDB")->ping;
        $record = $ping->findOne(array('record_id' => (int) $record_id));
        if (!$record) {
            self::redirect("/404");
        }

        $end_date = RequestMethods::get("enddate", date("Y-m-d"));
        $start_date = RequestMethods::get("startdate", date("Y-m-d", strtotime($end_date."-7 day")));
        $start_time = strtotime($start_date); $end_time = strtotime($end_date);

        $ping_stats = Registry::get("MongoDB")->ping_stats;
        $records = $ping_stats->find(array(
            'record_id' => (int) $record_id,
            'created' => array(
                '$gte' => new \MongoDate($start_time),
                '$lte' => new \MongoDate($end_time)
            )
        ));

        $obj = array();
        foreach ($records as $r) {
            $obj[] = array('y' => date('Y-m-d H:i:s', $r['created']->sec), 'a' => $r['latency']);
        }
        $view->set('ping', ArrayMethods::toObject($record))
            ->set('label', 'Latency')
            ->set('data', ArrayMethods::toObject($obj));
    }
}
