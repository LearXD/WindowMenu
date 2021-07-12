# WindowMenu
  
### API: 2.0.0
Uma pequena biblioteca para criação de menus customizados para a API 2.0.0 do Pocketmine-MP

## Exemplo de uso:
### Não sei usar o readme.md, me desculpem :,D


```php
// CRIANDO O OBJETO DA WINDOW
$window = new Window(
  /* Plugin */ $this->owner,
  /* Player */ $player, 
  /* string */ "Nome da sua Window", 
  function (InventoryTransactionEvent $event, Player $player, Item $item){
      if(#item->getId() !== 0){
        $event->setCancelled(true);
        $player->removeWindow(WindowManager::getPlayerWindow($player));
        $player->sendMessage("Você pegou o item: §f" . $item->getId());
      }
  },
  /* int */ 20);
  
// ADICIONAR UM ITEM NA WINDOW
$window->addItem(Item::get(Item::WOOL, Wool::PINK)->setCustomName("§dNome do Item"));

// ENVIAR A WINDOW PARA O PLAYER
$player->addWindow($window);
```

