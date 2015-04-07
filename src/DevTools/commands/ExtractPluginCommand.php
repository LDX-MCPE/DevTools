<?php

namespace DevTools\commands;

use DevTools\DevTools;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PharPluginLoader;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class ExtractPluginCommand extends DevToolsCommand {

  public function __construct(DevTools $plugin) {
    parent::__construct("extractplugin",$plugin);
    $this->setUsage("/extractplugin <plugin>");
    $this->setDescription("Extracts the source code from a Phar plugin.");
    $this->setPermission("devtools.command.extractplugin");
  }

  public function execute(CommandSender $sender,$commandLabel,array $args) {
    if(!$this->getPlugin()->isEnabled()) {
      return false;
    }
    if(!$this->testPermission($sender)) {
      return false;
    }
    if(count($args) === 0) {
      $sender->sendMessage(TextFormat::RED . "Usage: " . $this->usageMessage);
      return true;
    }
    $pluginName = trim(implode(" ",$args));
    if($pluginName === "" or !(($plugin = Server::getInstance()->getPluginManager()->getPlugin($pluginName)) instanceof Plugin)) {
      $sender->sendMessage(TextFormat::RED . "Plugin not loaded.");
      return true;
    }
    $description = $plugin->getDescription();
    if(!($plugin->getPluginLoader() instanceof PharPluginLoader)) {
      $sender->sendMessage(TextFormat::RED . "The plugin " . $description->getName() . "'s folder is not structured properly.");
      return true;
    }
    $folderPath = $this->getPlugin()->getDataFolder() . $description->getName() . "-master/";
    if(is_dir($folderPath)) {
      $sender->sendMessage("Plugin already exists, overwriting...");
    } else {
      @mkdir($folderPath);
    }
    $reflection = new \ReflectionClass("pocketmine\\plugin\\PluginBase");
    $file = $reflection->getProperty("file");
    $file->setAccessible(true);
    $pharPath = str_replace("\\","/",rtrim($file->getValue($plugin),"\\/"));
    foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pharPath)) as $fInfo) {
      $path = $fInfo->getPathname();
      @mkdir(dirname($folderPath . str_replace($pharPath,"",$path)),0755,true);
      file_put_contents($folderPath . str_replace($pharPath,"",$path),file_get_contents($path));
    }
    $sender->sendMessage("The plugin " . $description->getName() . " has been extracted to " . $folderPath);
    return true;
  }

}
