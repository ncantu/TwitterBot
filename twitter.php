<?php

trait Tdata {

	private static $dirRoot = 'data/';
	private static $ext     = '.json';

	public $id;
	public $type;
	public $score = 0;

	public function __construct() {

		$this->typeSet();
	}

	public function typeSet() {

		$this->type = get_class($this);

		return true;
	}

	public function fileNameGet() {

		$dir = self::$dirRoot.$this->type.DIRECTORY_SEPARATOR;

		if(is_dir($dir) === false) mkdir($dir);

		return $dir.$this->id.self::$ext;
	}

	public function exist() { 	

		$file = $this->fileNameGet();

		if(is_file(file) === false) return false;

		return $file;
	}

	public function save() {

		$this->score();
		$this->increment();

		$file    = $this->exist();
		$content = json_encode($this);

		return file_put_contents, content);
	}

	public function get() { 

		$file = $this->exist();

		if($file === false) return false;

		$content = file_get_contents($file);

		return json_decode($content);
	}

	public function increment() {

		$file = $this->exist();

		if($file === false) {

			$this->count = 1;

			return $this->count;
		}
		$obj = $this->get();

		foreach ($obj as $k => $v) $this->$k = $v;
			
		$this->count++;

		return $this->count,
	}	
}

class Tweet {

	use Tdata;

	public $user;
	public $hashtagList              = array();
	public $text;
	public $retweeted                = false;	
	public $favorited                = false;
	public $inReply                  = false;
	public $retweetCount             = 0;

	public static function constructFromApi($tweeJson) {

		$tweet               = new Tweet();
		$tweet->id           = $tweeJson->id;
		$tweet->userId       = $tweeJson->user->id;
		$tweet->retweeted    = $tweeJson->retweeted;
		$tweet->favorited    = $tweeJson->favorited;
		$tweet->retweetCount = $tweeJson->retweetCount;
		$tweet->inReply      = self::inReplySetFromApi($tweeJson);
		$tweet->hashtagList  = self::hashtagListFromApi($tweeJson);
		$tweet->user         = User::userFromApi($tweeJson);

		$tweet->textClean($tweeJson->text);		
		
		$tweet->save();	

		return $tweet;
	}

	public static function userFromApi($tweeJson){

		$user = User::constructFromApiTweet($tweeJson);

		return $user;
	}

	public static function hashtagListFromApi($tweeJson, $hashtagList = array()){

		foreach($tweeJson->entities->hashtags as $hashTag) {

			$hashtagList[] = HashTag::constructFromApiTweet($hashTag);
		}
		return $hashtagList;
	}

	public static function inReplySetFromApi($tweeJson) {

		if($tweeJson->in_reply_to_user_id_str !== null) {

			return true;
		}
		return false;
	}

	private function textClean($text) {

		$text = html_entity_decode($text);

		if(substr($text, 0, 3) === 'RT:') $text = substr($text, 3);
		if(substr($text, 0, 3) === 'RT ') $text = substr($text, 3);

		$text = str_replace(' RT:', ' ', $text);
		$text = str_replace(' RT ', ' ', $text);
		$text = str_replace(': ', ' ', $text);
		$text = str_replace(',', ' ', $text);
		$text = str_replace('. ', ' ', $text);
		$text = str_replace('!', ' ', $text);
		$text = str_replace('(', ' ', $text);
		$text = str_replace(')', ' ', $text);
		$text = str_replace('[', ' ', $text);
		$text = str_replace(']', ' ', $text);
		$text = str_replace("'", ' ', $text);
		$text = str_replace('"', ' ', $text);
		$text = str_replace('|', ' ', $text);
		
		while(strstr($text, '..') !== false || strstr($text, '  ') !== false) {

			$text = str_replace('..', '.', $text);
			$text = str_replace('  ', ' ', $text);
		}
		$this->text = $text;

		return true;
	}

	private function score($hashtagScore = 0) {

		if($this->retweeted === true) $this->score += TweetList::$scoreConf->tweet->retweeted;
		if($this->favorited === true) $this->score += TweetList::$scoreConf->tweet->favorited;
		if($this->retweetCount > 10)  $this->score += TweetList::$scoreConf->tweet->retweetedCountSup10;
		if($this->inReply === true)   $this->score += TweetList::$scoreConf->tweet->inReplyToUserId;

		hashtagList

		

		foreach ($this->hashtagList as $hashtag) {

			$hashtagScore += $hashtag->score;
		}

		$this->score  = $this->score * TweetList::$scoreConf->tweet->ratioList->tweet;
		$this->score += $this->user->score * TweetList::$scoreConf->tweet->ratioList->user;		
		$this->score += $hashtagScore * TweetList::$scoreConf->tweet->ratioList->hashtagList;

		return true;		
	}

}

class User {

	use Tdata;

	public $following;


	public static function constructFromApiTweet($tweeJson) {

		$user            = new User();
		$user->id        = $tweet->userId;
		$user->following = $tweet->following;

		$user->save();

		return $user;
	}

	private function score() {

		if(this->scoreFollowing === true) $this->score += TweetList::$scoreConf->user->following;

		return true;
	}

}

class HashTag {

	use Tdata;

	public static function constructFromApiTweet($tweeJsonHastagItem) {
		
		$tag      = new HashTag();
		$tag->id  = $tweeJsonHastagItem;

		$tag->save();

		return $tag;
	}

	private function score() {


	}
}

class TweetList {

	use Tdata;

	CONST CONF_FILE                    = 'twitter.json';
	CONST API_HOME_TIMELINE            = 'https://api.twitter.com/1.1/statuses/home_timeline.json?count=100';
	CONST API_SEARCH                   = 'https://api.twitter.com/1.1/search/tweets.json?q{hashTag}&count=10';
	CONST API_VERIFY_CREDENTIALS       = 'https://api.twitter.com/1.1/account/verify_credentials.json';
	CONST API_RETWEET                  = 'https://api.twitter.com/1.1/statuses/retweet/{id}.json';

	private $connection;
	private $userId;
	private $hour;
	private $list                      = array();
	private $nbPerHour;
	private $nbleft;
	private $sleepEach;

	public static $hashTagSearchedList;
	public static $scoreConf;

	public function __construct() {

		$confContent               = file_get_contents(self::CONF_FILE);
		$confObj                   = json_decode($confContent);
		$consumer_key              = $confObj->consumer_key; 
		$consumer_secret           = $confObj->consumer_secret; 
		$oauth_token               = $confObj->oauth_token; 
		$oauth_token_secret        = $confObj->oauth_token_secret; 
		$this->connection          = new TwitterOAuth($consumer_key, $consumer_secret, $oauth_token, $oauth_token_secret);
		self::$hashTagSearchedList = $confObj->hashTagSearchedList;
		self::$scoreConf           = $confObj->score;
		$this->idSet();

		$this->init();
	}

	private function init() {

		$this->score     = 0;
		$this->hour      = date('H', time());
		$var 			 = 'h'.$this->hour;
		$this->nbPerHour = $confObj->nbPerHourList->$var->nbPerHour;
		$this->nbleft	 = $this->nbPerHour;
		$this->sleepEach = (60 * 60) / $this->nbPerHour;
		$this->id        = date('YmdHis', time());
		$this->list      = array();

		return true;
	}

	private function idSet(){

		$query        = self::API_VERIFY_CREDENTIALS; 
		$content      = $this->connection->get($query);
		$this->userId = $content->id;

		return true;
	}

	public static function run($first = true, $tag = '{id}') {

		while(true) {

			$TweetList = new TweetList();

			$TweetList->renew($first);

			$first = false;

			foreach($this->list as $tweeJson) {

				$tweet                       = Tweet::constructFromApi();
				$TweetList->score           += $tweet->score;
				$scoreList[$tweet->score][]  = $tweet->score;

				$TweetList->nbleft--;
				sleep($this->sleepEach);
			}
			foreach($scoreList as $score => $sl) {

				foreach($sl as $tweet) {

					$query   = str_replace($tag, $tweet->id, self::API_RETWEET);
					$content = $this->connection->get($query);
				}
			}

		}
		return false;
	}

	public function renew($first = false, $tagS = array(), $tag = '{hashTag}') {

		if($first !== true AND (empty($this->list) === false || $this->nbleft !== 0 || date('H', time()) === $this->hour)) {

			sleep(60);

			return false;
		}
		$this->save();
		$this->init();
		
		$content = $this->connection->get(self::API_HOME_TIMELINE);	

		foreach($contentas $tweeJson) {

			if($tweeJson-> id === $this->userId) continue;
			if($tweeJson->retweeted === true) continue;

			$this->list[] =  $tweeJson;
		}
		foreach(self::$hashTagSearchedList as $hashTagSearched) {

			foreach($hashTagSearched->hashTagList as $hashtag => $v) {

				$tagS[$v][] = $hashtag;
			}
		}
		krsort($tagS);

		foreach($tagS as $priority => $list) {

			foreach($list as $hashTag) {

				$query = str_replace($tag, '%23'.$hashTag, self::API_SEARCH);
				$res   = $this->connection->get($query)

				foreach($res->statues as $tweeJson) {

					if($tweeJson-> id === $this->userId) continue;
					if($tweeJson->retweeted === true) continue;

					$this->list[] =  $tweet
				}
			}
		}
		return true;
	}
	private function score(){

		return true;
	}
}

TweetList:run();

?>


