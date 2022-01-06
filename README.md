 <h1 align="center"> WindowMenu Api v0.2 </h1>
<p align="center"> A small library for creating interactive windows. </p>

<h2 align="center"> 🔨 Usage: </h1>

<h3 align="center"> :nerd_face: Explanation: </h1>
<p>● Registering the item handler so that events are called: </p>

```php
// As a parameter you must pass the Main class!
\your\directory\Window::registerHandler($this);
```
<p>● You can call the class just passing 4 parameters! The first is a provider, which may be your Main class. The second a Position, depending on the situation you can pass an instance of Player! The third is a string containing the menu name. The fourth and last containing a menu context. Example of a context: </p>

```php
// The menu Callable receives 3 parameters, an InventoryTransacion Event (which can and should be canceled), a Player object, and an Item Object (which has been transitioned)
  
$callable = function (Window $window, Player $player, Item $item, InventoryTransactionEvent $event) {
  // doing a little item check...
  if($item->getId() == \pocketmine\item\Item::EMERALD and $item->getCustomName("§dItem Name")){
    // removing the window
    $player->removeWindow($window);
    // using the player variable '-'
    $player->sendMessage("§aYou used window!");
    // canceling event...
    $event->setCanceled(true);
  }
}
  
```

<p>● Now that we've created our context, we can call our class Window: </p>

```php
// Return the class from our window!
$window = new \your\directory\Window($player->getPosition(), "Window Name", Window::DOUBLE_CHEST, $callable);

// Adding an item to the window:
$window->setItem(15, \pocketmine\item\Item::get(\pocketmine\item\Item::EMERALD)->setCustomName("§dItem Name"));

// Sending the window to a player:
$player->addWindow($window);
```
<p> Done! 🐨</p>
