<?php
namespace LiteCoreAPI;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\Config;

class Main extends PluginBase {
    /** @var Config */
    private $economyConfig;

    public function onEnable() : void {
        @mkdir($this->getDataFolder());
        $this->economyConfig = new Config(
            $this->getDataFolder() . "economy.yml",
            Config::YAML,
            []
        );
        $this->getLogger()->info("§aLiteCoreAPI успешно запущен!");
    }

    public function onDisable() : void {
        $this->economyConfig->save();
        $this->getLogger()->info("§cLiteCoreAPI выключен.");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
        switch ($command->getName()) {
            // --- Базовые команды ---
            case "food":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§cЭту команду можно использовать только в игре.");
                    return true;
                }
                $sender->setFood(20);
                $sender->sendMessage("§aГолод утолён!");
                return true;

            case "heal":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§cЭту команду можно использовать только в игре.");
                    return true;
                }
                if (!$sender->isOp()) {
                    $sender->sendMessage("§cУ вас нет прав на использование этой команды.");
                    return true;
                }
                $sender->setHealth($sender->getMaxHealth());
                $sender->sendMessage("§aЗдоровье восстановлено!");
                return true;

            case "fly":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§cЭту команду можно использовать только в игре.");
                    return true;
                }
                if (!$sender->isOp()) {
                    $sender->sendMessage("§cУ вас нет прав на использование этой команды.");
                    return true;
                }
                $allowFlight = !$sender->getAllowFlight();
                $sender->setAllowFlight($allowFlight);
                $sender->sendMessage($allowFlight ? "§aРежим полёта включён!" : "§cРежим полёта выключен!");
                return true;

            case "gm":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§cЭту команду можно использовать только в игре.");
                    return true;
                }
                if (!$sender->isOp()) {
                    $sender->sendMessage("§cУ вас нет прав на использование этой команды.");
                    return true;
                }
                if (count($args) < 1) {
                    $sender->sendMessage("§cИспользуйте: /gm <0|1|2|3>");
                    return true;
                }
                $mode = (int) $args[0];
                if ($mode < 0 || $mode > 3) {
                    $sender->sendMessage("§cНеверный режим (0-Выживание, 1-Творчество, 2-Приключение, 3-Наблюдение)");
                    return true;
                }
                $sender->setGamemode($mode);
                $sender->sendMessage("§aРежим игры изменён на " . $this->getGamemodeName($mode) . "!");
                return true;

            // --- Экономика: Монеты ---
            case "money":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§cЭту команду можно использовать только в игре.");
                    return true;
                }
                $balance = $this->getBalance($sender, "money");
                $sender->sendMessage("§eВаш баланс: §6" . $balance . " монет");
                return true;

            case "pay":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§cЭту команду можно использовать только в игре.");
                    return true;
                }
                if (count($args) < 2) {
                    $sender->sendMessage("§cИспользуйте: /pay <игрок> <сумма>");
                    return true;
                }
                $amount = (int) $args[1];
                if ($amount <= 0) {
                    $sender->sendMessage("§cСумма должна быть положительной.");
                    return true;
                }
                $target = $this->getServer()->getPlayer($args[0]);
                if (!$target instanceof Player) {
                    $sender->sendMessage("§cИгрок не найден или не в сети.");
                    return true;
                }
                if ($this->getBalance($sender, "money") < $amount) {
                    $sender->sendMessage("§cНедостаточно монет.");
                    return true;
                }
                $this->reduceBalance($sender, "money", $amount);
                $this->addBalance($target, "money", $amount);
                $sender->sendMessage("§aВы перевели " . $amount . " монет игроку " . $target->getName());
                $target->sendMessage("§eИгрок " . $sender->getName() . " перевёл вам " . $amount . " монет.");
                return true;

            case "addmoney":
                if (!$sender->isOp()) {
                    $sender->sendMessage("§cУ вас нет прав на использование этой команды.");
                    return true;
                }
                if (count($args) < 2) {
                    $sender->sendMessage("§cИспользуйте: /addmoney <игрок> <сумма>");
                    return true;
                }
                $amount = (int) $args[1];
                if ($amount <= 0) {
                    $sender->sendMessage("§cСумма должна быть положительной.");
                    return true;
                }
                $target = $this->getServer()->getPlayer($args[0]);
                if (!$target instanceof Player) {
                    $sender->sendMessage("§cИгрок не найден или не в сети.");
                    return true;
                }
                $this->addBalance($target, "money", $amount);
                $sender->sendMessage("§aИгроку " . $target->getName() . " выдано " . $amount . " монет.");
                $target->sendMessage("§eВам выдано " . $amount . " монет.");
                return true;

            case "reducemoney":
                if (!$sender->isOp()) {
                    $sender->sendMessage("§cУ вас нет прав на использование этой команды.");
                    return true;
                }
                if (count($args) < 2) {
                    $sender->sendMessage("§cИспользуйте: /reducemoney <игрок> <сумма>");
                    return true;
                }
                $amount = (int) $args[1];
                if ($amount <= 0) {
                    $sender->sendMessage("§cСумма должна быть положительной.");
                    return true;
                }
                $target = $this->getServer()->getPlayer($args[0]);
                if (!$target instanceof Player) {
                    $sender->sendMessage("§cИгрок не найден или не в сети.");
                    return true;
                }
                $this->reduceBalance($target, "money", $amount);
                $sender->sendMessage("§aС баланса игрока " . $target->getName() . " списано " . $amount . " монет.");
                $target->sendMessage("§eС вашего баланса списано " . $amount . " монет.");
                return true;

            case "seemoney":
                if (!$sender->isOp()) {
                    $sender->sendMessage("§cУ вас нет прав на использование этой команды.");
                    return true;
                }
                if (count($args) < 1) {
                    $sender->sendMessage("§cИспользуйте: /seemoney <игрок>");
                    return true;
                }
                $target = $this->getServer()->getPlayer($args[0]);
                if (!$target instanceof Player) {
                    $sender->sendMessage("§cИгрок не найден или не в сети.");
                    return true;
                }
                $balance = $this->getBalance($target, "money");
                $sender->sendMessage("§eБаланс игрока " . $target->getName() . ": §6" . $balance . " монет");
                return true;

            // --- Экономика: Коины (донат-валюта) ---
            case "coins":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§cЭту команду можно использовать только в игре.");
                    return true;
                }
                $balance = $this->getBalance($sender, "coins");
                $sender->sendMessage("§eВаш баланс коинов: §b" . $balance . " коинов");
                return true;

            case "paycoins":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§cЭту команду можно использовать только в игре.");
                    return true;
                }
                if (count($args) < 2) {
                    $sender->sendMessage("§cИспользуйте: /paycoins <игрок> <сумма>");
                    return true;
                }
                $amount = (int) $args[1];
                if ($amount <= 0) {
                    $sender->sendMessage("§cСумма должна быть положительной.");
                    return true;
                }
                $target = $this->getServer()->getPlayer($args[0]);
                if (!$target instanceof Player) {
                    $sender->sendMessage("§cИгрок не найден или не в сети.");
                    return true;
                }
                if ($this->getBalance($sender, "coins") < $amount) {
                    $sender->sendMessage("§cНедостаточно коинов.");
                    return true;
                }
                $this->reduceBalance($sender, "coins", $amount);
                $this->addBalance($target, "coins", $amount);
                $sender->sendMessage("§aВы перевели " . $amount . " коинов игроку " . $target->getName());
                $target->sendMessage("§eИгрок " . $sender->getName() . " перевёл вам " . $amount . " коинов.");
                return true;

            case "addcoins":
                if (!$sender->isOp()) {
                    $sender->sendMessage("§cУ вас нет прав на использование этой команды.");
                    return true;
                }
                if (count($args) < 2) {
                    $sender->sendMessage("§cИспользуйте: /addcoins <игрок> <сумма>");
                    return true;
                }
                $amount = (int) $args[1];
                if ($amount <= 0) {
                    $sender->sendMessage("§cСумма должна быть положительной.");
                    return true;
                }
                $target = $this->getServer()->getPlayer($args[0]);
                if (!$target instanceof Player) {
                    $sender->sendMessage("§cИгрок не найден или не в сети.");
                    return true;
                }
                $this->addBalance($target, "coins", $amount);
                $sender->sendMessage("§aИгроку " . $target->getName() . " выдано " . $amount . " коинов.");
                $target->sendMessage("§eВам выдано " . $amount . " коинов.");
                return true;

            case "reducecoins":
                if (!$sender->isOp()) {
                    $sender->sendMessage("§cУ вас нет прав на использование этой команды.");
                    return true;
                }
                if (count($args) < 2) {
                    $sender->sendMessage("§cИспользуйте: /reducecoins <игрок> <сумма>");
                    return true;
                }
                $amount = (int) $args[1];
                if ($amount <= 0) {
                    $sender->sendMessage("§cСумма должна быть положительной.");
                    return true;
                }
                $target = $this->getServer()->getPlayer($args[0]);
                if (!$target instanceof Player) {
                    $sender->sendMessage("§cИгрок не найден или не в сети.");
                    return true;
                }
                $this->reduceBalance($target, "coins", $amount);
                $sender->sendMessage("§aС баланса игрока " . $target->getName() . " списано " . $amount . " коинов.");
                $target->sendMessage("§eС вашего баланса списано " . $amount . " коинов.");
                return true;

            case "seecoins":
                if (!$sender->isOp()) {
                    $sender->sendMessage("§cУ вас нет прав на использование этой команды.");
                    return true;
                }
                if (count($args) < 1) {
                    $sender->sendMessage("§cИспользуйте: /seecoins <игрок>");
                    return true;
                }
                $target = $this->getServer()->getPlayer($args[0]);
                if (!$target instanceof Player) {
                    $sender->sendMessage("§cИгрок не найден или не в сети.");
                    return true;
                }
                $balance = $this->getBalance($target, "coins");
                $sender->sendMessage("§eБаланс коинов игрока " . $target->getName() . ": §b" . $balance . " коинов");
                return true;

            // --- Топы ---
            case "topmoney":
                $top = $this->getTop("money");
                $sender->sendMessage("§eТоп-10 игроков по монетам:");
                $i = 1;
                foreach ($top as $name => $bal) {
                    $sender->sendMessage("§6$i. $name: $bal монет");
                    $i++;
                }
                return true;

            case "topcoins":
                $top = $this->getTop("coins");
                $sender->sendMessage("§eТоп-10 игроков по коинам:");
                $i = 1;
                foreach ($top as $name => $bal) {
                    $sender->sendMessage("§b$i. $name: $bal коинов");
                    $i++;
                }
                return true;

            default:
                return false;
        }
    }

    // ---------- Вспомогательные методы ----------
    private function getGamemodeName(int $mode) : string {
        $names = ["Выживание", "Творчество", "Приключение", "Наблюдение"];
        return $names[$mode] ?? "Неизвестно";
    }

    private function getBalance(Player $player, string $currency) : int {
        $name = strtolower($player->getName());
        return (int) $this->economyConfig->getNested("players.$name.$currency", 0);
    }

    private function setBalance(Player $player, string $currency, int $amount) : void {
        $name = strtolower($player->getName());
        $this->economyConfig->setNested("players.$name.$currency", $amount);
        $this->economyConfig->save();
    }

    private function addBalance(Player $player, string $currency, int $amount) : void {
        $this->setBalance($player, $currency, $this->getBalance($player, $currency) + $amount);
    }

    private function reduceBalance(Player $player, string $currency, int $amount) : void {
        $this->setBalance($player, $currency, max(0, $this->getBalance($player, $currency) - $amount));
    }

    private function getTop(string $currency) : array {
        $players = $this->economyConfig->getNested("players", []);
        $balances = [];
        foreach ($players as $name => $data) {
            if (isset($data[$currency])) {
                $balances[$name] = $data[$currency];
            }
        }
        arsort($balances);
        return array_slice($balances, 0, 10, true);
    }
}