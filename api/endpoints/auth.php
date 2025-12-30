<?php
/**
 * Endpoint per autenticazione
 * POST   /api/auth/login    - Login utente
 */

function handleAuthRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'POST':
            if ($action === 'login') {
                // Login utente
                validateRequired($data, ['login', 'password']);
                $userLogin = [
                    'login' => sanitizeInput($data['login']),
                    'password' => sanitizeInput($data['password'])
                ];
                
                $user = Login::selectByLogin($userLogin);
                
                if (empty($user) || count($user) == 0) {
                    sendErrorResponse("Login o password non riconosciute", 401);
                }
                
                // Verifica che l'utente abbia ruolo 3 (autorizzato)
                if ($user[0]['role'] != 3) {
                    sendErrorResponse("Utente non autorizzato a accedere a questa area", 403);
                }
                
                // Aggiorna ultimo accesso
                Login::addLastLoginById($user[0]['id']);
                
                // Restituisci i dati dell'utente (senza password)
                sendSuccessResponse([
                    'id' => (int)$user[0]['id'],
                    'login' => $user[0]['login'],
                    'nome' => $user[0]['nome'],
                    'role' => (int)$user[0]['role']
                ], "Login effettuato con successo");
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

