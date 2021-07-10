<p align="center">

  # WindowMenu
  
### API: 2.0.0
Uma pequena biblioteca para criação de menus customizados para a API 2.0.0 do Pocketmine-MP

## Exemplo de uso:
### Não sei usar o readme.me, me desculpem :,D
 </p>

```php
$window = new Window($this->owner, $event->getPlayer(), "Nome da sua Window", function (InventoryTransactionEvent $event, Player $player, Item $item){
            $event->setCancelled(true);
            $player->removeWindow(WindowManager::getPlayerWindow($player));
            $player->sendMessage("Você pegou o item: §f" . $item->getId());
        });
$window->addItem(Item::get(Item::WOOL, Wool::PINK)->setCustomName("§dNome do Item"));
/* Player */ $player->addWindow($window);
```

