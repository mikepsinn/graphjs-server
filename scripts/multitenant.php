<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require '../vendor/autoload.php';

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
 
 /**
 * This class contains functions needed to generate a 
 * new GraphJS tenant on a typical:
 * * Ubuntu 16.04 Linux OS
 * * PHP 7.1
 * * Nginx (as a proxy server)
 * * docker with redis and neo4j images 
 * system.
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
 final class MT {

     private $root;
     private $num;
     private $uuid;

     public function __construct(string $uuid = "", string $dir = "/etc/graphjs-server") {
         $this->root = $dir;
         $this->num = $this->findConfigNum();
         if(!empty($uuid)) {
             $this->uuid = $uuid;
            return;
         }
         try { 
             $uuid = Uuid::uuid4();
             $this->uuid = strtoupper($uuid->toString());
         } 
         catch (UnsatisfiedDependencyException $e) {
            // Some dependency was not met. Either the method cannot be called on a
            // 32-bit system, or it can, but it relies on Moontoast\Math to be present.
            die('Caught exception: ' . $e->getMessage());
        }
     }

     private function findConfigNum(): int 
     {
         $list = array_diff(scandir($this->root), array(".", ".."));
         rsort($list, SORT_NUMERIC);
         return ((int) $list[0])+1;
     }
 
    /**
     * Forms Env file
     *
     * Env file constitutes the main settings of the 
     * GraphJS application. It contains database host
     * configurations and all external resources needed
     * to run the application.
     * 
     * @param string $stream_key Stream is a service used for feeds
     * @param string $stream_secret Stream is a service used for feeds
     * @param string $mailgun_key Mailgun is a cloud service for SMTP
     * @param string $mailgun_domain Mailgun is a cloud service for SMTP
     * @return void
     */
  public function makeEnvFile(
      string $stream_key, 
      string $stream_secret, 
      string $mailgun_key = "", 
      string $mailgun_domain = "",
      string $founder_nickname,
      string $founder_email,
      string $founder_password
      ): void 
  {
      $template = file_get_contents(__DIR__ . "/templates/env.txt");
    $file_contents = sprintf(
        $template, 
        (string) (6378+$this->num),
        (string) (7686+$this->num),
        $mailgun_key, $mailgun_domain,
        $stream_key, $stream_secret,
        $founder_nickname, $founder_email, $founder_password
    );
    $env_dir = sprintf("%s/%s", $this->root, $this->num);
    mkdir($env_dir);
    file_put_contents($env_dir."/.env", $file_contents);
  }
  
  public function setupNginxConf() {
      $conf_file = "/etc/nginx/sites-enabled/default";
    $nginx = file_get_contents($conf_file);
    $seek = "location / {";
    $template = file_get_contents(__DIR__ . "/templates/nginx.txt");
    $replace = sprintf($template, $this->uuid, (string) ( 1337 + $this->num ) );
    $nginx = str_replace($seek, $replace, $nginx);
    file_put_contents($conf_file, $nginx, LOCK_EX);
  }
  
  public function reloadServers() {
      exec(sprintf("docker volume create vol-redis-%s", $this->num));
      exec(sprintf("docker volume create vol-neo4j-%s", $this->num));
        exec(sprintf("docker run -d -p %s:7687 --name neo4j-%s -v vol-neo4j-%s 75ae85cc12a7", (string) (7687+$this->num), $this->num, $this->num)); // docker neo4j
        exec(sprintf("docker run -d -p %s:6379 --name redis-%s -v vol-redis-%s c5355f8853e4",(string) (6379+$this->num), $this->num, $this->num)); // docker redis
        exec("supervisorctl reread && supervisorctl update && service nginx reload");
  }
  
  public function setupSupervisorConf(string $domain) {
    $filename = sprintf("/etc/supervisor/conf.d/gjs-%s.conf", (string) $this->num);
    $template = file_get_contents(__DIR__ . "/templates/supervisor.txt");
    $conf = sprintf($template, 
        (string) $this->num, 
        (string) $this->num, 
        (string) (1337+$this->num),
        $domain
    );
    file_put_contents($filename, $conf, LOCK_EX);
  }

  public function run(string $domain) {
      $this->setupNginxConf(); // ok
    $this->setupSupervisorConf($domain); // ok
    // 
    $this->reloadServers(); 

  }
 
 }