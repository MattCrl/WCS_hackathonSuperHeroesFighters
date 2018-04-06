<?php
/**
 * Created by PhpStorm.
 * User: wilder4
 * Date: 05/04/18
 * Time: 17:11
 */

namespace Model;

use GuzzleHttp\Client;

class Game
{
    private static $instance = null;

    private $players = [];

    private $currentPlayerIndex = -1;

    private $pickedSuperheroes;

    private $lastAction;

    private $log = [];

    /**
     *  INSTANCE
     */
    private function __construct()
    {
    }


    public static function getInstance()
    {
        // Create a new Game and store it into session
        // OR retrieve game from session

        $session = Session::getInstance();
        //If not defined in session => new game
        if (!isset($session->game)) {
            self::$instance = new self;
            $session->game = self::$instance;
        } else { //If defined in session => retrieve it
            self::$instance = $session->game;
        }

        return self::$instance;
    }

    public function saveToSession()
    {
        $session = Session::getInstance();
        $session->game = self::$instance;

    }

    public function destroy()
    {
        $session = Session::getInstance();
        unset($session->game);
    }

    /**
     * @return mixed
     */
    public function getLastAction()
    {
        return $this->lastAction;
    }

    /**
     * @param mixed $lastAction
     * @return Game
     */
    public function setLastAction($lastAction)
    {
        $this->lastAction = $lastAction;
        return $this;
    }


    /**
     * PLAYERS
     */

    public function addPlayer(int $id)
    {
        // Get superhero by id
        $found = null;
        $picked = $this->getPickedSuperheroes();

        foreach ($picked as $hero) {
            if ($hero->id == $id) {
                $found = $hero;
                break;
            }
        }

        $this->players[] = new Player($found);

    }


    public function getPlayers(): array
    {
        return $this->players;
    }


    /**
     * LOGIQUE DE JEU
     */

    /**
     * @return mixed
     */
    public function getCurrentPlayerIndex()
    {
        return $this->currentPlayerIndex;
    }

    /**
     * @param int $currentPlayerIndex
     */
    public function setCurrentPlayerIndex(int $currentPlayerIndex): void
    {
        $this->currentPlayerIndex = $currentPlayerIndex;
    }

    public function doAttack($id)
    {
        $attack = Player::ATTACKS[$id];
        if ($this->getCurrentPlayerIndex() == 0) {
            $defenser = 1;
        } else {
            $defenser = 0;
        }
        $playerDefenser = $this->getPlayers()[$defenser];
        $currentPlayer = $this->getPlayers()[$this->getCurrentPlayerIndex()];

        $attackCurrentPlayer = $currentPlayer->getStatAttack($attack);

        $calcDamage = $attackCurrentPlayer - ($attackCurrentPlayer * $playerDefenser->getDurability() / 200);

        $currentLife = $playerDefenser->getCurrentLife();
        $pointsToLoose = $calcDamage;
        $currentLife -= $pointsToLoose;

        //add To log

        $currentPlayerName = $currentPlayer->getName();
        $currentPlayerEnergy = $currentPlayer->getCurrentEnergy();
        $currentPlayer->setCurrentEnergy($currentPlayerEnergy - $attack['energy']);

        $playerDefenserName = $playerDefenser->getName();
        $attackName = $attack['name'];
        $verb = $attack['verb'];
        $addToLog = sprintf('%s %s "%s" et inflige %.2f points de dégâts à %s', $currentPlayerName, $verb, $attackName, $pointsToLoose, $playerDefenserName);

        $this->addToLog($addToLog);

        $playerDefenser->setCurrentLife($currentLife);

        return $attack;
    }

    public function nextTurn()
    {
        $currentPlayerIndex = $this->getCurrentPlayerIndex();

        if ($currentPlayerIndex === 0) {
            $nextPlayerIndex = 1;
        } else {
            $nextPlayerIndex = 0;
        }

        $currentPlayer = $this->getPlayers()[$this->getCurrentPlayerIndex()];

        $currentPlayerEnergy = $currentPlayer->getCurrentEnergy();
        $currentPlayer->setCurrentEnergy($currentPlayerEnergy + 10);

        $this->setCurrentPlayerIndex($nextPlayerIndex);

    }

    public function isOneKo()
    {
        $players = $this->getPlayers();
        foreach ($players as $player) {
            if ($player->getCurrentLife() < 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * LOG   0 => Machin commence
     *   1 => Tour 1 : Machin : Nom de l'attaque inflige - 20 à Machin2
     */

    public function addToLog($str)
    {
        $this->log[] = $str;

    }


    public function getLog(): array
    {
        return $this->log;
    }


    /**
     * API SUPERHEROES
     */

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getRandomSuperheroes(): array
    {
//      $idPersos = [30,60,70,106,107,140,149,201,226,238,242,303,309,339,346,529,536,579,589,638];
        $client = new Client(['base_uri' => 'https://cdn.rawgit.com/akabab/superhero-api/0.2.0/api/',]);


        $allSuperheroes = $client->request('GET', 'all.json');

        $body = $allSuperheroes->getBody();
        $allSuperheroes = json_decode($body->getContents());

        $rand_keys = array_rand($allSuperheroes, 24);

        $randomSuperheros = [];

        foreach ($rand_keys as $key) {
            $randomSuperheros[] = $allSuperheroes[$key];
        }
        return $randomSuperheros;

    }


    public function getPickedSuperheroes()
    {
        if (empty($this->pickedSuperheroes)) {
            $this->pickedSuperheroes = $this->getRandomSuperheroes();
            $this->saveToSession();
        }
        return $this->pickedSuperheroes;

    }

}
