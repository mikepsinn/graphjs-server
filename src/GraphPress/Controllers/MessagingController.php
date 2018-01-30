<?php
/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace GraphPress\Controllers;

use CapMousse\ReactRestify\Http\Request;
use CapMousse\ReactRestify\Http\Response;
use CapMousse\ReactRestify\Http\Session;
use Pho\Kernel\Kernel;
use Valitron\Validator;
use PhoNetworksAutogenerated\User;


class MessagingController extends \Pho\Server\Rest\Controllers\AbstractController 
{
    public function message(Request $request, Response $response, Session $session, Kernel $kernel)
    {
        $id = $session->get($request, "id");
        if(is_null($id)) {
            $this->fail($response, "You must be logged in to use this functionality");
            return;
        }
        $data = $request->getQueryParams();
        $v = new Validator($data);
        $v->rule('required', ['to', 'message']);
        if(!$v->validate()) {
            $this->fail($response, "Valid recipient and message are required.");
            return;
        }
        if(!preg_match("/^[a-zA-Z0-9_]{1,12}$/", $data["to"])) {
            $this->fail($response, "Invalid recipient");
            return;
        }
        if(empty($data["message"])) {
            $this->fail($response, "Message can't be empty");
            return;
        }
        /*
        $new_user = new User(
            $kernel, $kernel->graph(), $data["username"], $data["email"], $data["password"]
        );
        $session->set($request, "id", (string) $new_user->id());
        */
        $response->writeJson([
            "status"=>"success", 
            "id" => "ok" // (string) $new_user->id()
        ])->end();
    }
}
