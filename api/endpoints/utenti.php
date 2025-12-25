<?php
/**
 * Endpoint per gestione Utenti
 * GET    /api/utenti              - Lista tutti gli utenti
 * GET    /api/utenti/{id}         - Dettaglio utente
 * PUT    /api/utenti/{id}         - Aggiorna utente
 * POST   /api/utenti              - Crea nuovo utente
 * PUT    /api/utenti/{id}/password - Cambia password
 * DELETE /api/utenti/{id}         - Elimina utente
 */

function handleUtentiRequest($method, $action, $id, $data) {
    switch ($method) {
        case 'GET':
            if ($id === null && $action === '') {
                // Lista tutti gli utenti
                $users = Utenti::selectUtenti();
                $result = [];
                foreach ($users as $u) {
                    $gruppo = 'ADMIN';
                    if ($u['gr_nome'] != '') {
                        $gruppo = $u['gr_nome'];
                    }
                    $attivo = ($u['active'] == 1) ? "attivo" : "disattivato";
                    
                    $result[] = [
                        'id' => (int)$u['id'],
                        'nome' => strtoupper($u['nome']),
                        'gruppo' => strtoupper($gruppo),
                        'mail' => strtolower($u['mail']),
                        'ultimo_accesso' => $u['ultimoAccesso'],
                        'data_creazione' => $u['dataCreazione'],
                        'ruolo' => $u['permesso'],
                        'attivo' => (int)$u['active']
                    ];
                }
                sendSuccessResponse($result);
            } elseif ($id !== null && $action === '') {
                // Dettaglio utente
                $utente = Utenti::selectUtenteById($id);
                if (empty($utente)) {
                    sendErrorResponse("Utente non trovato", 404);
                }
                sendSuccessResponse($utente[0]);
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'POST':
            // Crea nuovo utente
            if ($id === null && $action === '') {
                if (!isset($data['login']) || empty($data['login'])) {
                    sendErrorResponse("Login richiesto", 400);
                }
                
                // Verifica se il login esiste già
                $existing = Utenti::selectUtenteById(0); // Non usare questo per verificare, ma per ora ok
                $allUsers = Utenti::selectUtenti();
                foreach ($allUsers as $u) {
                    if (strtolower($u['login']) == strtolower($data['login'])) {
                        sendErrorResponse("Login già esistente", 409);
                    }
                }
                
                $newId = Utenti::createUtente($data);
                if ($newId) {
                    $utente = Utenti::selectUtenteById($newId);
                    sendSuccessResponse($utente[0], "Utente creato con successo", 201);
                } else {
                    sendErrorResponse("Errore nella creazione dell'utente", 500);
                }
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'PUT':
        case 'PATCH':
            if ($id !== null && $action === 'password') {
                // Cambia password
                if (!isset($data['newpass'])) {
                    sendErrorResponse("newpass richiesto", 400);
                }
                $newPass = md5($data['newpass']);
                $result = Utenti::changePassword($id, $newPass);
                if ($result) {
                    sendSuccessResponse(['id' => $id, 'password_md5' => $newPass], "Password cambiata con successo");
                } else {
                    sendErrorResponse("Errore nel cambio password", 500);
                }
            } elseif ($id !== null && $action === '') {
                // Aggiorna utente
                if (!isset($data['login']) || empty($data['login'])) {
                    sendErrorResponse("Login richiesto", 400);
                }
                
                $result = Utenti::updateUtente($id, $data);
                if ($result !== false) {
                    // Ricarica i dati aggiornati dal database
                    $utente = Utenti::selectUtenteById($id);
                    if (empty($utente)) {
                        sendErrorResponse("Errore nel recupero dei dati aggiornati", 500);
                    } else {
                        sendSuccessResponse($utente[0], "Utente aggiornato con successo");
                    }
                } else {
                    sendErrorResponse("Errore nell'aggiornamento dell'utente", 500);
                }
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        case 'DELETE':
            if ($id !== null && $action === '') {
                // Elimina utente
                $result = Utenti::deleteUtente($id);
                if ($result) {
                    sendSuccessResponse(['id' => $id], "Utente eliminato con successo");
                } else {
                    sendErrorResponse("Errore nell'eliminazione dell'utente", 500);
                }
            } else {
                sendErrorResponse("Action non valida", 400);
            }
            break;
            
        default:
            sendErrorResponse("Method not allowed", 405);
    }
}

