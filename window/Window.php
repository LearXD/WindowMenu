<?php

/*
 *      ██╗░░░░░███████╗░█████╗░██████╗░██╗░░██╗██████╗░
 *      ██║░░░░░██╔════╝██╔══██╗██╔══██╗╚██╗██╔╝██╔══██╗
 *      ██║░░░░░█████╗░░███████║██████╔╝░╚███╔╝░██║░░██║
 *      ██║░░░░░██╔══╝░░██╔══██║██╔══██╗░██╔██╗░██║░░██║
 *      ███████╗███████╗██║░░██║██║░░██║██╔╝╚██╗██████╔╝
 *      ╚══════╝╚══════╝╚═╝░░╚═╝╚═╝░░╚═╝╚═╝░░╚═╝╚═════╝░
 *                    Author: LearXD#1044
 */

namespace bedwars\libs {

    use pocketmine\event\inventory\InventoryCloseEvent;
    use pocketmine\event\inventory\InventoryOpenEvent;
    use pocketmine\event\inventory\InventoryTransactionEvent;
    use pocketmine\event\Listener;
    use pocketmine\event\player\PlayerDropItemEvent;
    use pocketmine\inventory\BaseInventory;
    use pocketmine\inventory\CustomInventory;
    use pocketmine\inventory\FakeBlockMenu;
    use pocketmine\inventory\Inventory;
    use pocketmine\inventory\InventoryType;
    use pocketmine\item\Item;
    use pocketmine\level\Position;
    use pocketmine\nbt\NBT;
    use pocketmine\nbt\tag\ByteTag;
    use pocketmine\nbt\tag\CompoundTag;
    use pocketmine\nbt\tag\IntTag;
    use pocketmine\nbt\tag\NamedTag;
    use pocketmine\nbt\tag\StringTag;
    use pocketmine\network\protocol\BlockEntityDataPacket;
    use pocketmine\network\protocol\ContainerClosePacket;
    use pocketmine\network\protocol\ContainerOpenPacket;
    use pocketmine\network\protocol\UpdateBlockPacket;
    use pocketmine\Player;
    use pocketmine\plugin\Plugin;
    use pocketmine\scheduler\PluginTask;
    use pocketmine\scheduler\Task;
    use pocketmine\Server;
    use pocketmine\utils\BinaryStream;

    final class WindowManager
    {

        /** @var Window[] */
        protected static $players = [];

        /**
         * @param Player $player
         * @param Inventory $inventory
         * @return Inventory
         */
        public static function addPlayerWindow(Player $player, Window $inventory): Window
        {
            return self::$players[strtolower($player->getName())] = $inventory;
        }

        /**
         * @param Player $player
         * @return bool
         */
        public static function removePlayerWindow(Player $player): bool
        {
            if (isset(self::$players[strtolower($player->getName())])) {
                unset(self::$players[strtolower($player->getName())]);
            }
            return true;
        }

        /**
         * @param Player $player
         * @return Window|null
         */
        public static function getPlayerWindow(Player $player)
        {
            return self::$players[strtolower($player->getName())] ?? null;
        }
    }

    class WindowHandler implements Listener
    {

        /** @var  Plugin */
        protected $owner = null;

        /** @var Item[] */
        protected $transitionedItems = [];

        public function __construct(Plugin $plugin)
        {
            $this->owner = $plugin;
        }

        /**
         * @priority HIGHEST
         */
        public function drop(PlayerDropItemEvent $event) {
            $item = $event->getItem();
            if(isset($this->transitionedItems[$item->getCustomName()])) {
                unset($this->transitionedItems[$item->getCustomName()]);
                $event->setCancelled(true);
            }
        }

        public function close (InventoryCloseEvent $event) {
            $player = $event->getPlayer();
            if(WindowManager::getPlayerWindow($player)) {
                WindowManager::removePlayerWindow($player);
            }
        }

        public function transaction(InventoryTransactionEvent $event): bool
        {
            $transaction = $event->getQueue();
            $player = $transaction->getPlayer();

            if ($event->isCancelled()) {
                return false;
            }

            if ($window = WindowManager::getPlayerWindow($player)) {

                foreach ($transaction->getTransactions() as $trans) {
                    /** @var Item $item */
                    $item = $trans->getTargetItem();
                    try {
                        if ($item->getId() > 0) { // WIN10 ED BE LIKE

                            $window->getClosure()->call($window, $window, $player, $item, $event);

                            if($event->isCancelled()) {
                                if($item->getCustomName() != "") {
                                    $player->getInventory()->remove($item);
                                    $this->transitionedItems[$item->getCustomName()] = $item;
                                }
                                $player->getFloatingInventory()->remove($item);
                            }

                        }
                    } catch (\Exception $exception) {
                        var_dump($exception->getMessage());
                    }
                }

            }

            return true;
        }
    }

    class Window extends BaseInventory
    {

        const SINGLE_CHEST = 1;
        const DOUBLE_CHEST = 2;

        const OPEN_COUNTDOWN = 2;
        const CLOSE_COUNTDOWN = 4;


        /** @var string */
        protected $customName = "";

        /** @var int */
        protected $countdown = 10;

        /** @var int */
        protected $type = InventoryType::CHEST;
        /** @var int */
        protected $size = 27;

        /** @var Position */
        protected $pos = null;

        /** @var FakeBlockMenu */
        protected $holder = null;

        /** @var \Closure|callable */
        protected $closure = null;

        public static function registerHandler(Listener $plugin)
        {
            if ($plugin instanceof Plugin) {
                $plugin->getServer()->getPluginManager()->registerEvents(new WindowHandler($plugin), $plugin);
            } else {
                Server::getInstance()->getLogger()->alert('§cIt was not possible to register the Window system handler, as the passed class is not extended to Plugin');
            }
        }

        /**
         * Window constructor.
         * @param Position $position
         * @param string $name
         * @param callable $context
         * @param int $countdown
         * @param int $type
         * @param int $size
         */
        public function __construct(Position $position, string $name, int $size = self::SINGLE_CHEST, callable $context = null, int $countdown = 10, int $type = InventoryType::CHEST)
        {
            $this->pos = $position->add(0, 2);

            $this->customName = $name;
            $this->closure = $context;

            $this->countdown = $countdown;

            $this->type = $type;
            $this->size = $size;

            $this->holder = new FakeBlockMenu($this, $this->pos);
            parent::__construct($this->holder, InventoryType::get($type));
        }

        /**
         * @param Player $who
         */
        public function onOpen(Player $who)
        {

            if(!$who->isOnline()) {
                return false;
            }

            $pk = new UpdateBlockPacket();
            $pk->x = $this->pos->x;
            $pk->y = $this->pos->y;
            $pk->z = $this->pos->z;
            $pk->blockId = 54;
            $pk->blockData = 0;
            $pk->flags = UpdateBlockPacket::FLAG_ALL;
            $who->dataPacket($pk);

            $compound = new CompoundTag("", [
                new StringTag("id", InventoryType::CHEST),
                new StringTag("CustomName", $this->customName),
                new IntTag("x", (int) $this->pos->x),
                new IntTag("y", (int) $this->pos->y),
                new IntTag("z", (int) $this->pos->z)
            ]);

            if($this->size >= self::DOUBLE_CHEST) {
                $compound['pairx'] = new IntTag("pairx", ((int) $this->pos->getX()) + 1);
                $compound['pairz'] = new IntTag("pairz", (int) $this->pos->getZ());
            }

            $nbt = new NBT(NBT::LITTLE_ENDIAN);
            $nbt->setData($compound);

            $pk = new BlockEntityDataPacket();
            $pk->x = $this->holder->x;
            $pk->y = $this->holder->y;
            $pk->z = $this->holder->z;
            $pk->namedtag = $nbt->write();
            $who->dataPacket($pk);

            $inventory = $this;

            $this->process(function() use ($who, $inventory) {

                $inventory->viewers[spl_object_hash($who)] = $who;

                $pk = new ContainerOpenPacket();
                $pk->windowid = $who->getWindowId($inventory);
                $pk->type = $inventory->getType()->getNetworkType();
                $pk->slots = $inventory->getSize();
                $pk->x = $inventory->pos->getX();
                $pk->y = $inventory->pos->getY();
                $pk->z = $inventory->pos->getZ();

                $who->dataPacket($pk);
                $this->sendContents($who);
                $who->customInventory = $inventory;
                WindowManager::addPlayerWindow($who, $inventory);

            }, self::OPEN_COUNTDOWN);

            return true;
        }

        public function onClose(Player $who)
        {

            if (!$who->isOnline()) {
                return false;
            }

            if($who->customInventory !== null) {
                if($who->customInventory !== $this) {
                    return false;
                }
            }

            $pk = new UpdateBlockPacket();
            $pk->x = $this->holder->x;
            $pk->y = $this->holder->y;
            $pk->z = $this->holder->z;
            $pk->blockId = $who->getLevel()->getBlockIdAt($this->holder->x, $this->holder->y, $this->holder->z);
            $pk->blockData = $who->getLevel()->getBlockDataAt($this->holder->x, $this->holder->y, $this->holder->z);
            $pk->flags = UpdateBlockPacket::FLAG_NONE;
            $who->dataPacket($pk);

            $inventory = $this;

            $this->process(function () use ($who, $inventory) {
                unset($inventory->viewers[spl_object_hash($who)]);

                $pk = new ContainerClosePacket();
                $pk->windowid = $who->getWindowId($inventory);
                $who->dataPacket($pk);

                $who->customInventory = null;
                WindowManager::removePlayerWindow($who);

            }, self::CLOSE_COUNTDOWN);

            return true;
        }

        public function process(callable $callable, int $delay = self::OPEN_COUNTDOWN) {
            if($delay > 0) {
                Server::getInstance()->getScheduler()->scheduleDelayedTask(new class($callable) extends Task {

                    /** @var callable|\Closure */
                    protected $callable = null;

                    public function __construct(callable $callable) {
                        $this->callable = $callable;
                    }

                    public function onRun($currentTick)
                    {
                        $callable = $this->callable;
                        $callable();
                    }

                }, $delay);
            }
            return true;
        }

        public function setClosure(callable $callable) {
            return $this->closure = $callable;
        }

        /**
         * @return callable|\Closure
         */
        public function getClosure()
        {
            return $this->closure;
        }
    }
}
