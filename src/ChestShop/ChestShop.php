<?php

namespace ChestShop;
use pocketmine\item\ItemIds;
use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class ChestShop extends PluginBase
{
    public function onLoad()
    {
    }

    public function onEnable()
    {
        if (!file_exists($this->getDataFolder())) @mkdir($this->getDataFolder());
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this, new DatabaseManager($this->getDataFolder() . 'ChestShop.sqlite3')), $this);
    }

    public function onDisable()
    {
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool
    {
		switch ($command->getName()) {
			case "id":
				$name = array_shift($args);
				if(empty($name))
					return false;
				$constants = array_keys((new \ReflectionClass(ItemIds::class))->getConstants());
				foreach ($constants as $constant) {
					if (stripos($constant, (string)$name) !== false) {
						$id = constant(ItemIds::class."::$constant");
						$constant = str_replace("_", " ", $constant);
						$sender->sendMessage("ID:$id $constant");
					}
				}
				return true;

			default:
				return false;
		}
    }
}










