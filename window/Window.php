<?php

namespace learxd\anticheat\window {

    use pocketmine\event\inventory\InventoryTransactionEvent;
    use pocketmine\event\Listener;
    use pocketmine\inventory\CustomInventory;
    use pocketmine\inventory\Inventory;
    use pocketmine\inventory\InventoryHolder;
    use pocketmine\inventory\InventoryType;
    use pocketmine\level\Position;
    use pocketmine\math\Vector3;
    use pocketmine\nbt\NBT;
    use pocketmine\nbt\tag\CompoundTag;
    use pocketmine\nbt\tag\IntTag;
    use pocketmine\nbt\tag\StringTag;
    use pocketmine\network\protocol\BlockEntityDataPacket;
    use pocketmine\network\protocol\UpdateBlockPacket;
    use pocketmine\Player;
    use pocketmine\plugin\Plugin;
    use pocketmine\scheduler\PluginTask;

    final class WindowManager {

        protected static $players = [];

        public static function addPlayerWindow(Player $player, Inventory $inventory){
            return self::$players[strtolower($player->getName())] = $inventory;
        }

        public static function removePlayerWindow(Player $player){
            if(isset(self::$players[strtolower($player->getName())])) unset(self::$players[strtolower($player->getName())]);
            return true;
        }

        public static function getPlayerWindow(Player $player){
            if(isset(self::$players[strtolower($player->getName())]))
                return self::$players[strtolower($player->getName())];
            else
                return null;
        }
    }

    class WindowHolder extends Vector3 implements InventoryHolder {

        protected $inventory;

        public function __construct($x, $y, $z, Inventory $inventory){
            parent::__construct($x, $y, $z);
            $this->inventory = $inventory;
        }

        public function getInventory(){
            return $this->inventory;
        }

    }

    class Window extends CustomInventory implements Listener {

        /** @var string */
        protected $customName = "";
        /** @var \Closure|callable */
        protected $ctx = null;

        /** @var int  */
        protected $type = InventoryType::CHEST;
        /** @var int  */
        protected $size = 27;

        /** @var Position */
        protected $pos = null;
        /** @var WindowHolder */
        protected $holder = null;

        /** @var Plugin */
        protected $owner = null;

        public function __construct(Plugin $provider, Position $position, string $name, callable $context, int $type = InventoryType::CHEST, int $size = 27) {
            $this->customName = $name;
            $this->pos = $position->add(0, 2);
            $this->ctx = $context;
            $this->owner = $provider;
            /** NÃO PRECISA MAS... */
            $this->holder = new WindowHolder($this->pos->x, $this->pos->y, $this->pos->z, $this);
            $provider->getServer()->getPluginManager()->registerEvents($this, $provider);
            parent::__construct($this->holder, InventoryType::get($type));
        }


        public function setPosition(Position $position){
            return $this->pos = $position;
        }

        public function onOpen(Player $who){

            $pk = new UpdateBlockPacket();
            $pk->x = $this->holder->x;
            $pk->y = $this->holder->y;
            $pk->z = $this->holder->z;
            $pk->blockId = 54;
            $pk->blockData = 0;
            $pk->flags = UpdateBlockPacket::FLAG_ALL;
            $who->dataPacket($pk);

            $this->pos->getLevel()->updateAround($this->pos);

            $c = new CompoundTag("", [
                new StringTag("id", InventoryType::CHEST),
                new IntTag("x", (int) $this->pos->x),
                new IntTag("y", (int) $this->pos->y),
                new IntTag("z", (int) $this->pos->z),
                new StringTag("CustomName", $this->customName)
            ]);

            $nbt = new NBT(NBT::LITTLE_ENDIAN);
            $nbt->setData($c);

            $pk = new BlockEntityDataPacket();
            $pk->x = $this->holder->x;
            $pk->y = $this->holder->y;
            $pk->z = $this->holder->z;
            $pk->namedtag = $nbt->write();
            $who->dataPacket($pk);

            $this->owner->getServer()->getScheduler()->scheduleDelayedTask(new class($this->owner, $this, $who, function (Player $who){
                parent::onOpen($who);
                $this->sendContents($who);
                WindowManager::addPlayerWindow($who, $this);
            }) extends PluginTask {

                /** @var \Closure */
                protected $closure = null;

                /** @var Window */
                protected $window = null;

                /** @var Player */
                protected $who = null;

                public function __construct(Plugin $owner, Window $window, Player $who, callable $closure)
                {
                    $this->window = $window;
                    $this->closure = $closure;
                    $this->who = $who;
                    parent::__construct($owner);
                }

                public function onRun($currentTick)
                {
                    $this->closure->call($this->window, $this->who);
                }

            }, 10);

        }

        public function onClose(Player $who){
            $pk = new UpdateBlockPacket();
            $pk->x = $this->holder->x;
            $pk->y = $this->holder->y;
            $pk->z = $this->holder->z;
            $pk->blockId = $who->getLevel()->getBlockIdAt($this->holder->x, $this->holder->y, $this->holder->z);
            $pk->blockData = $who->getLevel()->getBlockDataAt($this->holder->x, $this->holder->y, $this->holder->z);
            $pk->flags = UpdateBlockPacket::FLAG_ALL;
            $who->dataPacket($pk);

            $this->owner->getServer()->getScheduler()->scheduleDelayedTask(new class($this->owner, $this, $who, function (Player $who){
                parent::onClose($who);
                WindowManager::removePlayerWindow($who);
            }) extends PluginTask {

                /** @var \Closure */
                protected $closure = null;

                /** @var Window */
                protected $window = null;

                /** @var Player */
                protected $who = null;

                public function __construct(Plugin $owner, Window $window, Player $who, callable $closure)
                {
                    $this->window = $window;
                    $this->closure = $closure;
                    $this->who = $who;
                    parent::__construct($owner);
                }

                public function onRun($currentTick)
                {
                    $this->closure->call($this->window, $this->who);
                }

            }, 10);

        }

        public function onTransaction(InventoryTransactionEvent $event){
            $transaction = $event->getQueue();
            $player = $transaction->getPlayer();

            if($event->isCancelled())
                return false;

            if(WindowManager::getPlayerWindow($player) != $this)
                return false;

            foreach($transaction->getTransactions() as $trans){
                $item = $trans->getTargetItem();
                try {
                    $this->ctx->call($this, $event, $player, $item);
                } catch (\Exception $exception){
                    var_dump($exception);
                }
            }
        }
    }
}