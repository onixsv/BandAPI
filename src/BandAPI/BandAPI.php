<?php
declare(strict_types=1);

namespace BandAPI;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\Internet;
use function implode;
use function var_dump;

class BandAPI extends PluginBase{

	protected static $token;

	protected static $band;

	protected function onEnable() : void{
		$config = new Config($this->getDataFolder() . "Config.yml", Config::YAML, [
			"token" => "",
			"band-key" => ""
		]);
		self::$token = $config->getNested("token", "");
		self::$band = $config->getNested("band-key", "");
	}

	public static function getToken() : string{
		return self::$token;
	}

	public static function getBand() : string{
		return self::$band;
	}

	public static function getData() : ?array{
		$data = Internet::getURL("https://openapi.band.us/v2.1/bands?access_token=" . self::$token);
		$data = json_decode($data->getBody(), true) ?? ["result_code" => 200, ["result_data" => ["bands" => []]]];

		foreach($data["result_data"]["bands"] as $k => $v){
			if($v["band_key"] === self::getBand()){
				return $v;
			}
		}

		return null;
	}

	public static function sendPost(string $text){
		if((self::$token ?? "") === "" || (self::$band ?? "") === ""){
			return;
		}
		Server::getInstance()->getAsyncPool()->submitTask(new class($text, self::$token, self::$band) extends AsyncTask{
			private $text;
			private $token;
			private $band;

			public function __construct(string $text, string $token, string $band){
				$this->text = $text;
				$this->token = $token;
				$this->band = $band;
			}

			public function onRun() : void{
				$url = "https://openapi.band.us/v2.2/band/post/create?";
				$text = [
					"access_token" => $this->token,
					"band_key" => $this->band,
					"content" => $this->text
				];
				$query = http_build_query($text, "", "&");
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
				curl_setopt($ch, CURLOPT_PROXY_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
				curl_setopt($ch, CURLOPT_POST, true);
				$data = curl_exec($ch);
				curl_close($ch);
				$this->setResult($data);
			}

			public function onCompletion() : void{
				var_dump($this->getResult());
			}
		});
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!$command->testPermission($sender)){
			return false;
		}
		$args = implode(" ", $args);
		self::sendPost($args);
		return true;
	}
}