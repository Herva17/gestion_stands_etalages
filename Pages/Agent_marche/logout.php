<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';

$agent = new AgentMarche();
$result = $agent->logout();

header('Location: /login.php?success=logout');
exit;