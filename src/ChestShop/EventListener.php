<?php

namespace ChestShop;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\Listener;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BaseSign;
use pocketmine\block\utils\SignText;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\item\ItemFactory;
use pocketmine\world\World;
use pocketmine\world\Position;
use pocketmine\math\Vector3;
use pocketmine\block\tile\Chest as TileChest;

class EventListener implements Listener
{
    private $plugin;
    private $databaseManager;

    public function __construct(ChestShop $plugin, DatabaseManager $databaseManager)
    {
        $this->plugin = $plugin;    
        $this->databaseManager = $databaseManager;
    }

    public function onPlayerInteract(PlayerInteractEvent $event)
    {
        $block = $event->getBlock();
        $player = $event->getPlayer();
        

        switch ($block->getID()) {

            case ItemIds::SIGN_POST:
            case ItemIds::WALL_SIGN:
                if (($shopInfo = $this->databaseManager->selectByCondition([
                        "signX" => $block->getPosition()->getX(),
                        "signY" => $block->getPosition()->getY(),
                        "signZ" => $block->getPosition()->getZ()
                    ])) === false) return;
                if ($shopInfo['shopOwner'] === $player->getName()) {
                    $player->sendMessage("Cannot purchase from your own shop!");
                    return;
                }
                $buyerMoney = $this->plugin->getServer()->getPluginManager()->getPlugin("PocketMoney")->getMoney($player->getName());
                if (!is_numeric($buyerMoney)) { // Probably $buyerMoney is instance of SimpleError
                    $player->sendMessage("Couldn't acquire your money data!");
                    return; 
                }
                if ($buyerMoney < $shopInfo['price']) {
                    $player->sendMessage("Your money is not enough!");
                    return;
                }
                /** @var TileChest $chest */
                $chest = $player->getWorld()->getTile(new Vector3($shopInfo['chestX'], $shopInfo['chestY'], $shopInfo['chestZ']));
                $itemNum = 0;
                $pID = $shopInfo['productID'];
                $pMeta = $shopInfo['productMeta'];
                for ($i = 0; $i < $this->getSize(); $i++) {
                    $item = $chest->getInventory()->getItem($i);
                    // use getDamage() method to get metadata of item
                    if ($item->getID() === $pID and $item->getMeta() === $pMeta) $itemNum += $item->getCount();
                }
                if ($itemNum < $shopInfo['saleNum']) {
                    $player->sendMessage("This shop is out of stock!");
                    if (($p = $this->plugin->getServer()->getPlayerByPrefix($shopInfo['shopOwner'])) !== null) {
                        $p->sendMessage("Your ChestShop is out of stock! Replenish ID:$pID:$pMeta!");
                    }
                    return;
                }

                //TODO Improve this
                $itemfactory = new ItemFactory();
                $player->getInventory()->addItem(clone $itemfactory->get((int)$shopInfo['productID'], (int)$shopInfo['productMeta'], (int)$shopInfo['saleNum']));

                $tmpNum = $shopInfo['saleNum'];
                for ($i = 0; $i < $this->getSize(); $i++) {
                    $item = $chest->getInventory()->getItem($i);
                    // Use getDamage() method to get metadata of item
                    if ($item->getID() === $pID and $item->getMeta() === $pMeta) {
                        if ($item->getCount() <= $tmpNum) {
                            $chest->getInventory()->setItem($i, $itemfactory->get(ItemIds::AIR, 0, 0));
                            $tmpNum -= $item->getCount();
                        } else {
                            $chest->getInventory()->setItem($i, $itemfactory->get($item->getID(), $pMeta, $item->getCount() - $tmpNum));
                            break;
                        }
                    }
                }
                $this->plugin->getServer()->getPluginManager()->getPlugin("PocketMoney")->payMoney($player->getName(), $shopInfo['shopOwner'], $shopInfo['price']);

                $player->sendMessage("Completed transaction");
                if (($p = $this->plugin->getServer()->getPlayerByPrefix($shopInfo['shopOwner'])) !== null) {
                    $p->sendMessage("{$player->getName()} purchased ID:$pID:$pMeta {$shopInfo['price']}PM");
                }
                break;

            case ItemIds::CHEST:
                $shopInfo = $this->databaseManager->selectByCondition([
                    "chestX" => $block->getPosition()->getX(),
                    "chestY" => $block->getPosition()->getY(),
                    "chestZ" => $block->getPosition()->getZ()
                ]);
                if ($shopInfo !== false && $shopInfo['shopOwner'] !== $player->getName()) {
                    $player->sendMessage("This chest has been protected!");
                    $event->cancel();
                }
                break;

            default:
                break;
        }
    }

    public function onPlayerBreakBlock(BlockBreakEvent $event)
    {
        $block = $event->getBlock();
        $player = $event->getPlayer();

        switch ($block->getID()) {
            case ItemIds::SIGN_POST:
            case ItemIds::WALL_SIGN:
                $condition = [
                    "signX" => $block->getPosition()->getX(),
                    "signY" => $block->getPosition()->getY(),
                    "signZ" => $block->getPosition()->getZ()
                ];
                $shopInfo = $this->databaseManager->selectByCondition($condition);
                if ($shopInfo !== false) {
                    if ($shopInfo['shopOwner'] !== $player->getName()) {
                        $player->sendMessage("This sign has been protected!");
                        $event->cancel();
                    } else {
                        $this->databaseManager->deleteByCondition($condition);
                        $player->sendMessage("Closed your ChestShop");
                    }
                }
                break;

            case ItemIds::CHEST:
                $condition = [
                    "chestX" => $block->getPosition()->getX(),
                    "chestY" => $block->getPosition()->getY(),
                    "chestZ" => $block->getPosition()->getZ()
                ];
                $shopInfo = $this->databaseManager->selectByCondition($condition);
                if ($shopInfo !== false) {
                    if ($shopInfo['shopOwner'] !== $player->getName()) {
                        $player->sendMessage("This chest has been protected!");
                        $event->cancel();
                    } else {
                        $this->databaseManager->deleteByCondition($condition);
                        $player->sendMessage("Closed your ChestShop");
                    }
                }
                break;

            default:
                break;
        }
    }

    public function onSignChange(SignChangeEvent $event)
    {
        $shopOwner = $event->getPlayer()->getName();
        $line = $event->getOldText()->getLines();
        $saleNum = $line[1];
        $price = $line[2];
        $productData = explode(":", $line[3]);
        $pID = $this->isItem($id = array_shift($productData)) ? (int)$id : false;
		$pMeta = ($meta = array_shift($productData)) ? (int)$meta : 0;
        $block = $event->getBlock();
        $sign = $block->getPosition();
        $player = $event->getPlayer();
        $player->sendMessage($pMeta);
        
        // Check sign format...
        if ($line[0] !== "") return;
        if (!is_numeric($saleNum) or $saleNum <= 0) return;
        if (!is_numeric($price) or $price < 0) return;
        if ($pID === false) return;
        if (($chest = $this->getSideChest($sign)) === false) return;
        $chestX = $chest->getPosition()->getX();
        $chestY = $chest->getPosition()->getY();
        $chestZ = $chest->getPosition()->getZ();
        $blockfactory = new BlockFactory();
        $productName = $blockfactory->get($pID,$pMeta)->getName();
        $event->setNewText(new SignText([
            $line[0] = $shopOwner,
            str_replace($line[1], "Amount:$saleNum", $line[1]),
            str_replace($line[2], "Price:$price", $line[2]),
            str_replace($line[3], "$productName:[$pID:$pMeta]", $line[3])
        ]));

        $this->databaseManager->registerShop($shopOwner, $saleNum, $price, $pID, $pMeta, $sign, $chestX, $chestY, $chestZ);
    }

    private function getSideChest(Position $pos)
    {
		$block = $pos->getWorld()->getBlock(new Vector3($pos->getX() + 1, $pos->getY(), $pos->getZ()));
		if ($block->getID() === ItemIds::CHEST) return $block;
		$block = $pos->getWorld()->getBlock(new Vector3($pos->getX() - 1, $pos->getY(), $pos->getZ()));
		if ($block->getID() === ItemIds::CHEST) return $block;
		$block = $pos->getWorld()->getBlock(new Vector3($pos->getX(), $pos->getY() - 1, $pos->getZ()));
		if ($block->getID() === ItemIds::CHEST) return $block;
		$block = $pos->getWorld()->getBlock(new Vector3($pos->getX(), $pos->getY(), $pos->getZ() + 1));
		if ($block->getID() === ItemIds::CHEST) return $block;
		$block = $pos->getWorld()->getBlock(new Vector3($pos->getX(), $pos->getY(), $pos->getZ() - 1));
		if ($block->getID() === ItemIds::CHEST) return $block;
		return false;
    }

    private function isItem($id)
    {
        $itemfactory = new ItemFactory;
		return $itemfactory->isRegistered((int) $id);
    }

    public function getSize()
    {
		return 27;
	}
} 