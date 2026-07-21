# Riepilogo tecnico delle task — Gestionale Divani

## Colore e nota colore in Magazzino → Uscite
La tabella Uscite mostrava prodotto, cliente, quantità e stato, ma non recuperava i dati presenti in `order_product_variables`. La colonna colore/nota non esisteva e la sola nota colore non veniva salvata se tessuto e colore erano entrambi null. 

Estesa la query del componente Livewire con i join verso `order_product_variables` e `colors`. Aggiunta la colonna `Colore / Nota` e il filtro su nome colore, codice colore e `color_notes`. Corretto anche il salvataggio della sola nota colore quando tessuto e colore sono null.

---

## Ricerca ordini cliente per riferimento
Il campo `orders.reference` era già presente e visualizzato nella tabella, ma non era incluso nei filtri e negli ordinamenti disponibili.

Aggiunto `orders.reference` alla whitelist dei filtri e degli ordinamenti. Implementata ricerca parziale con `LIKE` e integrato il filtro nell’header della tabella ordini.

---

## Data DDT basata sulla data di inserimento ordine
Il DDT veniva generato usando la data corrente tramite `Carbon::today()`, sia per `issued_at` sia per l’anno della numerazione progressiva.

La generazione del DDT usa `orders.created_at` per `issued_at` e per l’anno della numerazione progressiva. Nei DDT accorpati viene usato come capofila l’ordine creato per primo.

---

## Ordini in fase Spedizione non apribili
La logica era legata al vecchio valore numerico della fase Spedizione e la ricerca/ristampa dei DDT verificava in modo non coerente il collegamento tra documento e ordine.

Allineata la logica alla nuova fase `ProductionPhase::SHIPPING`. Corretti apertura riga, drawer DDT e verifica di appartenenza del documento, supportando sia `ddts.order_id` sia la relazione tramite `ddt_rows.order_item_id`.

---

## Riferimento ordine, tessuto e colore nel DDT
Il PDF DDT caricava prodotto e riga ordine, ma non le relazioni di tessuto e colore. Il riferimento cliente non veniva riportato e nei documenti accorpati non erano elencati tutti gli ordini coinvolti.

Esteso l’eager loading del servizio PDF. Nel DDT vengono mostrati numero ordine, riferimento cliente, tessuto, colore e nota colore. Nei DDT accorpati vengono elencati tutti gli ordini coinvolti.

---

## Prezzo a `0,00 €` nella conferma ordine
Durante l’editing di una riga esistente, il componente caricava il prezzo salvato ma richiamava subito il ricalcolo automatico. Una quotazione assente o non valida poteva quindi sostituire il valore persistito con `0`.

Durante l’editing di una riga esistente viene sospeso il ricalcolo automatico del prezzo. Il valore persistito non viene più sostituito da una quotazione vuota o da un fallback a zero.

---

## Tessuto e colore nella conferma pubblica
La pagina pubblica mostrava prodotto, quantità e prezzo, ma non caricava né visualizzava tessuto, colore e nota colore. La nota veniva inoltre costruita tramite output HTML non escapato. 

Il controller carica `items.variable.fabric` e `items.variable.color`. La view pubblica mostra tessuto, colore e nota colore con output Blade escapato.

---

## Prezzo esistente sovrascritto a zero
Riaprendo una riga ordine, il prezzo poteva essere ricalcolato e sostituito prima del salvataggio. Il backend non distingueva correttamente tra prezzo già persistito e fallback del listino.

Corretto il flusso frontend e backend affinché il prezzo salvato venga mantenuto durante apertura, modifica e risalvataggio dell’ordine.

---

## Prezzo inserito successivamente alla creazione ordine
`OrderUpdateService` considerava una modifica soltanto quando cambiavano quantità, prodotto, varianti o data. Una modifica del solo prezzo restituiva “Nessuna modifica” e non aggiornava `order_items.unit_price`.

`OrderUpdateService` salva anche modifiche che riguardano soltanto prezzo, sconto o nota colore. Le operazioni di stock e procurement vengono eseguite solo quando cambiano quantità, prodotto, varianti o data di consegna.

---

## Accorpamento di più ordini in un DDT
La generazione DDT era progettata per un singolo ordine e il drawer cercava i documenti soltanto tramite `ddts.order_id`. Non esisteva un’azione bulk per creare un unico DDT da più ordini.

Aggiunta azione bulk in Magazzino → Uscite. Il service valida stesso tipo cliente, stesso cliente, stesso indirizzo, stessa zona e righe effettivamente spedibili. I colli vengono sommati e il DDT è visibile anche dagli ordini secondari.

---

## Rimozione step Taglio, Fusto e Spugna
Il flusso produttivo era composto da sette fasi numeriche: Inserito, Taglio, Cucito, Fusto, Spugna, Assemblaggio e Spedizione. I valori erano utilizzati da enum, eventi, work order, consumo componenti, DDT e viste di avanzamento.

Ridotto il flusso produttivo a `Inserito → Cucito → Assemblaggio → Spedizione`. Aggiornati enum, avanzamento/rollback, consumo componenti, work order, PDF e riferimenti hardcoded. Aggiunta migration dati per rimappare le vecchie fasi `0–6` nelle nuove `0–3`.

---

## Alert a 15 e 20 giorni dalla consegna
Esistevano tabella e modello `Alert`, ma il controller era vuoto, non era presente una vista operativa e il comando iniziale non era schedulato né idempotente. Gli alert non erano cliccabili e non esisteva un counter nella sidebar.

Implementato comando schedulato che crea alert per ordini ancora in produzione con consegna a 15 o 20 giorni. Aggiunta deduplicazione tramite `dedupe_key`, elenco alert, stato letto/non letto, apertura ordine collegato e counter degli alert non letti nella sidebar.

---

## Clienti: rimozione P. IVA e C.F.
P. IVA e Codice fiscale erano presenti nel form cliente, nello stato Alpine, nella validazione e nei metodi create/update. I valori erano utilizzati anche da documenti storici.

Rimossi i campi da form, stato Alpine, validazione e create/update. Le colonne database non sono state eliminate, così i valori storici restano disponibili.

---

## Clienti: città e note
La città era già disponibile in `customer_addresses`, ma non era mostrata correttamente nell’elenco clienti. Il campo note non esisteva nella tabella `customers`.

La città viene letta dall’indirizzo di spedizione esistente. Aggiunto `customers.notes` tramite migration e integrato il campo in modello, controller e form. La lista clienti usa `LEFT JOIN` per non escludere clienti senza indirizzo.

---

## Email di ritorno con conferma ordine in PDF
L’email di esito non includeva alcun allegato. Le prime versioni del PDF erano collegate alla mail sbagliata, utilizzavano variabili non definite e condividevano contenuti interni destinati ai commerciali.

Il PDF viene generato nella mail di esito positivo. Cliente e commerciali ricevono invii separati; il cliente non riceve dati interni sui PO. Il PDF include dati ordine, cliente, indirizzo, prodotti, varianti, prezzi, totale e numero colli.

---

## Materiale disponibile ma non impegnato
L’avanzamento controllava soltanto le prenotazioni già presenti in `stock_reservations`. Materiale fisicamente disponibile ma non ancora impegnato veniva quindi considerato mancante.

Prima dell’avanzamento viene verificato lo stock realmente libero. Se sufficiente, il sistema crea la prenotazione, registra il movimento `reserve` e prosegue. La selezione di eventuali ordini donatori usa `v_order_item_phase_qty` invece di `current_phase`.

---

## Persistenza filtri e pagina precedente
I filtri erano presenti, ma dopo create/update/delete o apertura di alcuni componenti si perdeva lo stato della lista. In Livewire il reset pagina veniva eseguito anche per proprietà non collegate ai filtri.

Per i prodotti viene conservato l’URL completo della lista. Per Magazzino → Uscite vengono mantenuti fase, filtri, ordinamento, `perPage` e pagina corrente, senza reset durante apertura di modal o drawer.

---

## Numero di colli nell’ordine
La tabella `ddts` possedeva già il campo `packages`, mentre l’ordine cliente non disponeva di un valore equivalente.

Aggiunto `orders.packages` tramite migration. Il campo è gestito in creazione, modifica, caricamento e reset del modal. Nei DDT singoli viene copiato; nei DDT accorpati viene sommato.

---

## Gestione reale varianti TESSU 
Negli avanzamenti e nei consumi veniva scalato sempre il componente fittizio `TESSU-00001` ignorando il reale tessuto e colore selezionato nell'ordine. Questo falsava il magazzino.

Introdotto `TessuComponentResolver`. Nei calcoli BOM e nei consumi il sistema ora risolve esattamente il componente TESSU reale basandosi su `fabric_id` e `color_id`. Il placeholder generico resta valido solo per lo storico già in database.

---

## Riferimento ordine nelle Uscite
Nella vista Magazzino → Uscite non era possibile visualizzare né filtrare i documenti in base al riferimento ordine fornito dal cliente.

Integrata la colonna "Riferimento" nella tabella. Il dato è ora visibile, ricercabile nei filtri testuali, ordinabile e stampabile nei PDF.

---

## Race condition e perdita note nei Resi
L'apertura rapida della modale Resi andava in crash per il caricamento asincrono delle variabili. Inoltre, in fase di creazione le note inserite andavano perse e i rientri a magazzino non imponevano un ordine di origine.

Risolta la race condition usando i dati forniti nativamente dal backend. Allineato stabilmente il campo `notes` su tutto il flusso (db, controller, frontend) ed estesa la validazione per rendere obbligatorio l'ordine in caso di restock.

---

## Integrità varianti storiche nei Resi
Il frontend bloccava la modifica (es. cambio quantità) di righe storiche in cui tessuto o colore erano stati rimossi dalla whitelist. Il backend consentiva invece di modificarne l'identità nativa.

Implementato bypass frontend per la validazione whitelist esclusivamente sulle varianti storiche inalterate. Blindato il controller backend in modo che prodotto, tessuto e colore di una riga esistente non possano mai più essere modificati.