<?php

namespace DevTools;

use DevTools\commands\ExtractPluginCommand;
use FolderPluginLoader\FolderPluginLoader;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\network\protocol\Info;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLoadOrder;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class DevTools extends PluginBase implements CommandExecutor {

  public function onLoad() {
    $this->getServer()->getCommandMap()->register("devtools",new ExtractPluginCommand($this));
  }

  public function onEnable() {
    @mkdir($this->getDataFolder());
    $this->getServer()->getPluginManager()->registerInterface("FolderPluginLoader\\FolderPluginLoader");
    $this->getServer()->getPluginManager()->loadPlugins($this->getServer()->getPluginPath(),["FolderPluginLoader\\FolderPluginLoader"]);
    $this->getServer()->enablePlugins(PluginLoadOrder::STARTUP);
  }

  public function onCommand(CommandSender $sender,Command $command,$label,array $args) {
    switch(strtolower($command->getName())) {
      case "makeplugin":
        if(isset($args[0]) and $args[0] === "FolderPluginLoader") {
          return $this->makePluginLoader($sender,$command,$label,$args);
        }
        return $this->makePluginCommand($sender,$command,$label,$args);
      case "makeserver":
        return $this->makeServerCommand($sender,$command,$label,$args);
      default:
        return false;
    }
  }

  private function makePluginLoader(CommandSender $sender,Command $command,$label,array $args) {
    $pharPath = $this->getDataFolder() . "FolderPluginLoader.phar";
    if(file_exists($pharPath)) {
      $sender->sendMessage("Phar plugin already exists, overwriting...");
      @unlink($pharPath);
    }
    $phar = new \Phar($pharPath);
    $phar->setMetadata(["name" => "FolderPluginLoader","version" => "1.0.0","main" => "FolderPluginLoader\\Main","api" => ["1.0.0"],"depend" => [],"description" => "Loader of folder plugins","authors" => ["PocketMine Team"],"website" => "https://github.com/PocketMine/DevTools","creationDate" => time()]);
    $phar->setStub("<?php\n__HALT_COMPILER();");
    $phar->setSignatureAlgorithm(\Phar::SHA1);
    $phar->startBuffering();
    $phar->addFromString("plugin.yml","name: FolderPluginLoader\nversion: 1.0.0\nmain: FolderPluginLoader\\Main\napi: [1.0.0]\nload: STARTUP");
    $phar->addFile($this->getFile() . "src/FolderPluginLoader/FolderPluginLoader.php","src/FolderPluginLoader/FolderPluginLoader.php");
    $phar->addFile($this->getFile() . "src/FolderPluginLoader/Main.php","src/FolderPluginLoader/Main.php");
    foreach($phar as $file => $finfo) {
      if($finfo->getSize() > (1024 * 512)) {
        $finfo->compress(\Phar::GZ);
      }
    }
    $phar->stopBuffering();
    $sender->sendMessage("The plugin FolderPluginLoader has been created in " . $pharPath);
    return true;
  }

  private function makePluginCommand(CommandSender $sender,Command $command,$label,array $args) {
    $pluginName = trim(implode(" ",$args));
    if($pluginName === "" or !(($plugin = Server::getInstance()->getPluginManager()->getPlugin($pluginName)) instanceof Plugin)) {
      $sender->sendMessage(TextFormat::RED . "Plugin not loaded.");
      return true;
    }
    $description = $plugin->getDescription();
    if(!($plugin->getPluginLoader() instanceof FolderPluginLoader)) {
      $sender->sendMessage(TextFormat::RED . "The plugin " . $description->getName() . "'s folder is not structured properly.");
      return true;
    }
    $pharPath = $this->getDataFolder() . $description->getName() . ".phar";
    if(file_exists($pharPath)) {
      $sender->sendMessage("Phar plugin already exists, overwriting...");
      @unlink($pharPath);
    }
    $phar = new \Phar($pharPath);
    $phar->setMetadata(["name" => $description->getName(),"version" => $description->getVersion(),"main" => $description->getMain(),"api" => $description->getCompatibleApis(),"depend" => $description->getDepend(),"description" => $description->getDescription(),"authors" => $description->getAuthors(),"website" => $description->getWebsite(),"creationDate" => time()]);
    if($description->getName() === "DevTools") {
      $phar->setStub("<?php\nrequire(\"phar://\" . __FILE__ . \"/src/DevTools/ConsoleScript.php\");\n__HALT_COMPILER();");
    } else {
      $phar->setStub("<?php\necho \"PocketMine-MP plugin " . $description->getName() . " v" . $description->getVersion() . "\nThis plugin has been generated using DevTools v" . $this->getDescription()->getVersion() . " on " . date("F jS, Y") . " at " . date("g:i A e.") . "\n----------------\n\";\nif(extension_loaded(\"phar\")) {\n  $phar = new \Phar(__FILE__);\n  foreach($phar->getMetadata() as $key => $value) {\n    echo ucfirst($key) . \": \" . (is_array($value) ? implode(\", \",$value) : $value) . \"\n\";\n  }\n}\n__HALT_COMPILER();");
    }
    $phar->setSignatureAlgorithm(\Phar::SHA1);
    $reflection = new \ReflectionClass("pocketmine\\plugin\\PluginBase");
    $file = $reflection->getProperty("file");
    $file->setAccessible(true);
    $filePath = rtrim(str_replace("\\", "/", $file->getValue($plugin)), "/") . "/";
    $phar->startBuffering();
    foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filePath)) as $file) {
      $path = ltrim(str_replace(["\\",$filePath],["/",""],$file),"/");
      if($path{0} === "." or strpos($path,"/.") !== false) {
        continue;
      }
      $phar->addFile($file, $path);
      $sender->sendMessage("[DevTools] Adding $path");
    }
    foreach($phar as $file => $finfo) {
      if($finfo->getSize() > 524288) {
        $finfo->compress(\Phar::GZ);
      }
    }
    $phar->stopBuffering();
    $sender->sendMessage("The plugin " . $description->getName() . " has been created in " . $pharPath);
    return true;
  }

  private function makeServerCommand(CommandSender $sender,Command $command,$label,array $args) {
    $server = $sender->getServer();
    $pharPath = $this->getDataFolder() . $server->getName() . ".phar";
    if(file_exists($pharPath)) {
      $sender->sendMessage("Phar file already exists, overwriting...");
      @unlink($pharPath);
    }
    $phar = new \Phar($pharPath);
    $phar->setMetadata(["name" => $server->getName(),"version" => $server->getPocketMineVersion(),"api" => $server->getApiVersion(),"minecraft" => $server->getVersion(),"protocol" => Info::CURRENT_PROTOCOL,"creationDate" => time()]);
    $phar->setStub("<?php define(\"pocketmine\\\\PATH\",\"phar://\" . __FILE__ . \"/");\nrequire_once(\"phar://\" . __FILE__ . \"/src/pocketmine/PocketMine.php\");\n__HALT_COMPILER();");
    $phar->setSignatureAlgorithm(\Phar::SHA1);
    $phar->startBuffering();
    $filePath = substr(\pocketmine\PATH,0,7) === "phar://" ? \pocketmine\PATH : realpath(\pocketmine\PATH) . "/";
    $filePath = rtrim(str_replace("\\","/",$filePath),"/") . "/";
    foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filePath . "src")) as $file) {
      $path = ltrim(str_replace(["\\",$filePath],["/",""],$file),"/");
      if($path{0} === "." or strpos($path, "/.") !== false or substr($path,0,4) !== "src/") {
        continue;
      }
      $phar->addFile($file,$path);
      $sender->sendMessage("[DevTools] Adding $path");
    }
    foreach($phar as $file => $finfo){
      if($finfo->getSize() > 524288) {
        $finfo->compress(\Phar::GZ);
      }
    }
    $phar->stopBuffering();
    $sender->sendMessage($server->getName() . " " . $server->getPocketMineVersion() . " Phar file has been created in " . $pharPath);
    return true;
  }

}
