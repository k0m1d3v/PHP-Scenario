# Artigiani Finder (Admin View)

Admin panel per gestire professionisti artigiani, città e professioni. Permette di inserire, modificare, filtrare ed eliminare i professionisti con relative professioni associate.

## Stack
- PHP (compatibile con 5.x; niente type hint/`??`)
- MySQL (charset utf8mb4)
- HTML/CSS semplice (no framework)

## Avvio locale
1) Configura `db.php` con le credenziali MySQL (default: `root` senza password, DB `artigiani_finder`).
2) Importa lo schema SQL (vedi sezione "Modello logico").
3) Avvia il server PHP/Apache e apri `index.php` nel browser.

## Modello E/R
- **Entità**: `citta`, `professione`, `professionista`.
- **Associazione molti-a-molti**: `professionista_professione` collega `professionista` a `professione`.
- **Cardinalità**:
	- Una `citta` ha molti `professionista`; ogni `professionista` appartiene a una sola `citta` (1:N).
	- Un `professionista` può avere molte `professione`; una `professione` può essere associata a molti `professionista` (N:M via tabella ponte).
<img width="621" height="352" alt="modello logico" src="https://github.com/user-attachments/assets/f443f79d-6d22-4519-bdd3-5807c4951586" />


### Diagramma
```
citta (1) ───< professionista >───< (N) professionista_professione >─── (N) professione
```

## Modello logico (relazionale)
- `citta(idCitta PK, nome, provincia)`
- `professione(idProfessione PK, nome)`
- `professionista(idProfessionista PK, nome, telefono, email, descrizione, tariffa_oraria, disponibilita, idCitta FK→citta.idCitta)`
- `professionista_professione(idProfessionista FK→professionista.idProfessionista, idProfessione FK→professione.idProfessione, PK(idProfessionista, idProfessione))`
<img width="1046" height="500" alt="image" src="https://github.com/user-attachments/assets/9429e870-b3b5-4457-b836-2c1cd2151ab4" />

## Funzioni principali
- Inserimento e modifica professionista (con città, tariffa, disponibilità, descrizione, email/telefono opzionali).
- Associazione di una o più professioni al professionista.
- Filtri per professione, città, tariffa massima e disponibilità.
- Eliminazione di un professionista (con cleanup delle associazioni).






