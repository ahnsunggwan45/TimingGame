<?php

namespace baaam;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\scheduler\Task;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use onebone\economyapi\EconomyAPI;

class event_noon extends PluginBase implements Listener
{
    private $count = false;
    private $rand = 10;
    private $fail = [];
    private $success = [];
    private $economy;
    private $prefix = "§l§b[§f눈치게임§b] §r§f";
    private $data, $db;

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new NoonStartTask ($this), 1200 * 3);
        $this->fail ["names"] = [];
        $this->success ["names"] = [];
        $this->economy = EconomyAPI::getInstance();
        @mkdir($this->getDataFolder());
        $this->data = new Config ($this->getDataFolder() . "data.yml", Config::YAML, [
            "rank" => []
        ]);
        $this->db = $this->data->getAll();
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer();
        if (!isset ($this->db ["rank"] [$player->getName()])) {
            $this->db ["rank"] [strtolower($player->getName())] = 0;
        }
    }

    public function onChat(PlayerChatEvent $event)
    {
        $chat = $event->getMessage();
        $player = $event->getPlayer();
        $count = $this->count;
        if ($count == false)
            return;
        if (!is_numeric($chat))
            return;
        if (isset ($this->success ["names"] [$player->getName()])) {
            $player->sendMessage($this->prefix . "이미 한번 참여하셨네요.. 참여 불가능..");
            return true;
        }
        if (isset ($this->fail ["names"] [$player->getName()])) {
            $player->sendMessage($this->prefix . "이미 탈락하셨네요 ㅠㅠ.. 다음 눈치게임을 노려보세요!");
            return true;
        }
        if ($chat <= $count) {
            $player->sendMessage($this->prefix . "아쉽지만 탈락! §a참가비 100원§f을 걷어갑니다..");
            $this->economy->reduceMoney($player, 100);
            $this->fail ["names"] [$player->getName()] = false;
        } elseif ($chat == $count + 1) {
            $player->sendMessage($this->prefix . "성공! 축하합니다! 1000원을 드립니다~");
            $player->sendMessage($this->prefix . "현재 {$player->getName()} 님이 성공하신 눈치게임 횟수는.. {$this->db["rank"][strtolower($player->getName())]}번 입니다!");
            $this->count = $chat;
            $this->economy->addMoney($player, 1000);
            $this->success ["names"] [$player->getName()] = true;
            $this->db ["rank"] [strtolower($player->getName())]++;
            $this->save();
            $this->getServer()->broadcastMessage($this->prefix . $player->getName() . "님이 §a{$chat}!");
            if ($chat == $this->rand) {
                $this->getServer()->broadcastMessage($this->prefix . "끝!");
                $this->count = false;
                unset ($this->fail ["names"]);
                unset ($this->success ["names"]);
                $this->success ["names"] = [];
                $this->fail ["names"] = [];
            }
        }
    }

    public function dataReset()
    {
        $this->getServer()->broadcastMessage($this->prefix . "끝!");
        $this->count = false;
        unset ($this->fail ["names"]);
        unset ($this->success ["names"]);
        $this->success ["names"] = [];
        $this->fail ["names"] = [];
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        $command = $command->getName();
        if ($command == "눈치게임") {
            if (!isset ($args [0]))
                $args [0] = 'x';
            switch ($args [0]) {
                case "순위" :
                    $count = 0;
                    $arr = $this->db ["rank"];
                    arsort($arr);
                    foreach ($arr as $k => $v) {
                        $count++;
                        $sender->sendMessage("§c[ §f{$count}위§c ] §f{$k}님, 성공횟수: {$v}번");
                        if ($count == 10)
                            return true;
                    }
                    break;
                case "내순위" :
                    $sender->sendMessage($this->prefix . "당신의 순위는 {$this->getRank($sender->getName())}위 이며 성공횟수는 {$this->getSuccessCount($sender->getName())} 회 입니다.");
                    break;
                case "순위초기화" :
                    if (!$sender->isOp())
                        return true;
                    unset ($this->db ["rank"]);
                    $this->db ["rank"] = [];
                    $this->getServer()->broadcastMessage($this->prefix . "관리자 {$sender->getName()}님에 의해 눈치게임 순위가 초기화됐습니다.");
                    break;
                case "시작" :
                    if (!$sender->isOp())
                        return true;
                    if ($this->count !== false) {
                        $sender->sendMessage($this->prefix . "이미 눈치게임이 진행중입니다.");
                        return true;
                    }
                    $this->getServer()->broadcastMessage($this->prefix . $sender->getName() . "님에 의해 눈치게임이 강제시작됐습니다.");
                    $this->Start();
                    break;
                default :
                    $this->help($sender);
                    if ($sender->isOp()) {
                        $sender->sendMessage($this->prefix . "/눈치게임 시작");
                        $sender->sendMessage($this->prefix . "/눈치게임 순위초기화");
                    }
                    break;
            }
        }
        return true;
    }

    public function help(CommandSender $sender)
    {
        $sender->sendMessage($this->prefix . "/눈치게임 순위");
        $sender->sendMessage($this->prefix . "/눈치게임 내순위");
    }

    public function getSuccessCount($name)
    {
        $name = strtolower($name);
        if (isset ($this->db ["rank"] [$name])) {
            return $this->db ["rank"] [$name];
        }
    }

    public function getRank($name)
    {
        $name = strtolower($name);
        $count = 0;
        $arr = $this->db ["rank"];
        arsort($arr);
        foreach ($arr as $k => $v) {
            $count++;
            if ($k == $name)
                return $count;
        }
    }

    public function Start()
    {
        $rand = mt_rand(5, 13);
        $this->rand = $rand;
        $this->getServer()->broadcastMessage($this->prefix . "시작! 1 (2부터 말하세요 ^^*) {$rand}까지 ㄱㄱㄱ!");
        $this->count = 1;
    }

    public function save()
    {
        $this->data->setAll($this->db);
        $this->data->save();
    }

    public function onDisable()
    {
        $this->save();
    }

    public function count()
    {
        return $this->count;
    }
}

class NoonStartTask extends Task
{
    private $plugin;

    publiC function __construct(event_noon $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onRun(int $currentTick)
    {
        if ($this->plugin->count() == false) {
            $this->plugin->Start();
        } else {
            $this->plugin->dataReset();
        }
    }
}