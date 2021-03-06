<?php
/**
 * Description of fakereferer
 *
 * @author Faizan Ayubi
 */
use Framework\RequestMethods as RequestMethods;
use Framework\Registry as Registry;

class FakeReferer extends Admin {

	public function index($hash) {
		$this->noview();
		if ($hash != NULL) {
            $spoof = base64_decode($hash);
            echo "<!DOCTYPE HTML><meta charset=\"UTF-8\"><meta http-equiv=\"refresh\" content=\"1; url=http://example.com\"><script>window.location.href=\"" . $spoof . "\"</script><title>Page Redirection</title>If you are not redirected automatically, follow the <a href='" . $spoof . "'>link</a>";
            exit;
	    }
	}

	/**
	 * @before _secure, memberLayout, _check
	 */
	public function create() {
		$this->seo(array(
            "title" => "Submit Your FakeReferer",
            "view" => $this->getLayoutView()
        ));
		$view = $this->getActionView();

		if (RequestMethods::post("action") == "submitTrigger") {
			$title = RequestMethods::post("title");
			$url = RequestMethods::post("url");
			$keyword = RequestMethods::post("keyword");
			$referer = RequestMethods::post("referer");
			$tld = RequestMethods::post("tld");

			switch ($referer) {
				case 'blank':
					$googl = Registry::get("googl");
		            $object = $googl->shortenURL("http://b.onlinedatafile.com/?hash=".base64_encode($url));
					$fakereferer = new \Referer(array(
						"user_id" => $this->user->id,
						"title" => $title,
						"url" => $url,
						"short_url" => $object->id,
						"keyword" => "",
						"referer" => $referer,
						"tld" => "NONE",
						"live" => true
					));
					$fakereferer->save();
					$response["success"] = true;
					break;
				
				default:
					$fakereferer = new \Referer(array(
						"user_id" => $this->user->id,
						"title" => $title,
						"url" => $url,
						"short_url" => "",
						"keyword" => $keyword,
						"referer" => $referer,
						"tld" => $tld,
						"live" => false
					));
					$response = $this->_shortUrl($fakereferer);
					break;
			}

			if (isset($response["success"])) {
				$view->set("success", 'Your request has been submiited. See status <a href="/fakereferer/manage">Manage</a>');
			} elseif (isset($response["error"])) {
				$view->set("success", "ERROR: ". $response["error"]);
			}
		}
	}

	/**
	 * @before _secure, memberLayout, _check
	 */
	public function manage() {
		$this->seo(array(
            "title" => "Manage FakeReferer URLs",
            "view" => $this->getLayoutView()
        ));
		$view = $this->getActionView();

		$page = RequestMethods::get("page", 1);
		$limit = RequestMethods::get("limit", 10);
		$count = \Referer::count(array("user_id = ?" => $this->user->id));

		$referers = \Referer::all(array("user_id = ?" => $this->user->id));

		$view->set(array(
			"referers" => $referers,
			"page" => $page,
			"limit" => $limit,
			"count" => $count
		));
	}

	/**
	 * @before _secure, _admin
	 */
	public function all() {
		$this->seo(array(
            "title" => "Manage FakeReferer URLs",
            "view" => $this->getLayoutView()
        ));
		$view = $this->getActionView();

		$page = RequestMethods::get("page", 1);
		$limit = RequestMethods::get("limit", 10);
		$count = \Referer::count(array());

		$referers = \Referer::all(array());
		$view->set(array(
			"referers" => $referers,
			"page" => $page,
			"limit" => $limit,
			"count" => $count
		));
	}

	/**
	 * @before _secure, memberLayout
	 */
	public function edit($ref_id) {
		$this->seo(array(
            "title" => "Submit Your FakeReferer",
            "view" => $this->getLayoutView()
        ));
		$view = $this->getActionView();

		$referer = \Referer::first(array("id = ?" => $ref_id));
		if ((!$referer || $referer->user_id != $this->user->id) && !$this->user->admin) {
			$this->redirect("/member");
		}

		if ($referer->short_url) {
			$this->redirect("/fakereferer/manage");
		}

		if (RequestMethods::post("action") == "editSubmission") {
			$referer->title = RequestMethods::post("title");
			$referer->url = RequestMethods::post("url");
			$referer->keyword = RequestMethods::post("keyword");

			$referer->save();
			$view->set("success", "Edited successfully!");
		}

		$view->set("referer", $referer);
	}

	/**
	 * @before _secure
	 */
	public function remove($ref_id) {
		$this->noview();
		$referer = \Referer::first(array("id = ?" => $ref_id));
		if (!$referer) {
			$this->redirect("/member");
		}

		$this->delete("Referer", $ref_id);
	}

	/**
	 * @before _secure, _admin
	 */
	public function shortUrl($ref_id) {
		$this->seo(array(
            "title" => "Submit Your FakeReferer",
            "view" => $this->getLayoutView()
        ));
		$view = $this->getActionView();
		$referer = \Referer::first(array("id = ?" => $ref_id));
		if (!$referer) {
			$this->redirect("/admin");
		}
		if (RequestMethods::post("action") == "approve") {
			$url = RequestMethods::post("url");
			if (!$url) {
				return;
			}
			switch ($referer->referer) {
				case 'twitter':	// User will automatically enter short url
					$referer->short_url = $url;
					$referer->live = true;
					break;
				
				default:
					$googl = Registry::get("googl");
		            $object = $googl->shortenURL("http://trafficmonitor.ca/fakereferer/index/".base64_encode($url));
					$referer->short_url = $object->id;
					$referer->live = true;
					break;
			}
			$referer->save();
			$view->set("success", 'Referer Approved. See <a href="/fakereferer/all">Manage</a>');
		}
		$view->set("referer", $referer);
	}

	protected function _shortUrl($referer) {
		if ($referer->referer == "google") {
			exec("node ". APP_PATH.'/application/libraries/NodeSEO/fakereferer.js '. "'{$referer->url}'", $output, $return);
			if ($return !== 0) { // Most probable cause: Server IP Blocked by Google
				$this->log('******** Error ********');
				$this->log('Fake Referer not working');
				return ["error" => "Something went wrong"];
			}

			$result = $output[0];
			if ($result == "error") {
				return ["error" => "Site not indexed by google"];
			}

			$vars = $this->_parse($result);
			$base_url = 'http://www.google.com/url?sa=t&rct=j&q='.urlencode($referer->keyword).'&esrc=s&source=web&cd=62&cad=rja&ved='.$vars["ved"].'&url='.urlencode($referer->url).'&ei=HlyPUMO3FMSPrge8y4DwAQ&usg='. $vars["usg"];

			$googl = Registry::get("googl");
            $object = $googl->shortenURL("http://trafficmonitor.ca/fakereferer/index/".base64_encode($base_url));
            $referer->short_url = $object->id;
			$referer->live = true;
			$referer->save();

			return ["success" => true];
		}
	}

	protected function _parse($google_js) {
		$str = str_replace("return rwt(", "", $google_js);
		$str = str_replace(")", "", $str);

		$pieces = explode(",", $str);

		$return = array();
		$return["usg"] = str_replace("'", "", $pieces[5]);
		$return["ved"] = str_replace("'", "", $pieces[7]);
		return $return;
	}
	
}
