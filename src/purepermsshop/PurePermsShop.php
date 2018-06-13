<?php

namespace purepermsshop;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\utils\TextFormat;

use onebone\economyapi\EconomyAPI;
use pocketmine\event\block\BlockBreakEvent;

class PurePermsShop extends PluginBase implements Listener{
	private $shops = [];

	private $pp = null, $economy = null;

	public function onEnable(){
		if(!file_exists($this->getDataFolder())){
			mkdir($this->getDataFolder());
		}

		if(!is_file($this->getDataFolder() . "shops.json")){
			file_put_contents($this->getDataFolder() . "shops.json", "[]");
		}

		$this->pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
		if($this->pp === null){
			$this->getLogger()->critical("PurePerms is not detected.");
			return;
		}

		$this->economy = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
		if($this->economy === null){
			$this->getLogger()->critical("EconomyAPI is not detected.");
			return;
		}

		$this->shops = json_decode(file_get_contents($this->getDataFolder()."shops.json"), true);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function SignChange(SignChangeEvent $event){
		$lines = $event->getLines();
		if (!$event->getPlayer()->hasPermission('purepermsshop')) {
			return true;
		}
		if($lines[0] === "permsshop"){
			$player = $event->getPlayer();
			$to = trim($lines[1]);
			$req = trim($lines[2]);
			$cost = trim($lines[3]);

			if($req !== "" and ($group = $this->pp->getGroup($req)) === null){
				return;
			}

			if(($target = $this->pp->getGroup($to)) === null){
				return;
			}

			if(!is_numeric($cost)){
				return;
			}

			$this->shops[self::getKey($event->getBlock())] = [
				$to, $req === '' ? null : $req, (float) $cost
			];

			$event->setLine(0, "§a| PermsShop |");
			$event->setLine(1, "§aGroup:§f " . $to);
			$event->setLine(2, "§aRequiredGroup:§f " . ($req === '' ? '§l없음': $req));
			$event->setLine(3, "§aCost§f: $cost");

			$player->sendMessage("§4성공적으로 생성되었습니다.");
			file_put_contents($this->getDataFolder() . "shops.json", json_encode($this->shops));
		}
	}

	public function onTouch(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			if(isset($this->shops[self::getKey($event->getBlock())])){
				$shop = $this->shops[self::getKey($event->getBlock())];

				$group = $this->pp->getUserDataMgr()->getGroup($player);
				if($shop[1] === null or $group !== null and $group->getName() === $shop[1]){
					$to = $this->pp->getGroup($shop[0]);
					if($to !== null){
						if($this->economy->reduceMoney($player, $shop[2]) === EconomyAPI::RET_SUCCESS){
							$this->pp->getUserDataMgr()->setGroup($player, $to, null, -1);
							$player->sendMessage(TextFormat::GREEN . "You Bought Group.");
						}
					}else{
						$player->sendMessage(TextFormat::RED . "Invalid Group");
					}
				}else{
					$player->sendMessage(TextFormat::RED . "You are not in Required Group");
				}
			}
		}
	}
	public function blockBreak(BlockBreakEvent $event) {
		if (isset($this->shops[self::getKey($event->getBlock())])) {
			if(!$event->getPlayer()->hasPermission('purepermsshop')) {
				$event->getPlayer()->sendMessage(TextFormat::RED . 'You are not allowed to remove PermsShop');
				$event->setCancelled(true);
				return false;
			} else {
				unset($this->shops[self::getKey($event->getBlock())]);
				file_put_contents($this->getDataFolder() . "shops.json", json_encode($this->shops));
				$event->getPlayer()->sendMessage(TextFormat::WHITE . 'removed');
				return true;
			}
		}
		return true;
	}
	public static final function getKey($pos){
		return $pos->x .':' . $pos->y .':' . $pos->z . ':' . $pos->level->getFolderName();
	}
}
