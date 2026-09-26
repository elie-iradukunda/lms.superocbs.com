# Logistics Management System - Presentation Guide

Uyu ni umwongozo wuzuye (Presentation Guide) w'uburyo ushobora kwerekana iyi system (Logistics MVC) uhereye hasi (from scratch) ukerekana ubushobozi bwayo bwose wifashishije ingero zifatika (Practical Examples) ziri muri system.

## 1. INTANGIRIRO (Introduction)
Tangira usobanurira abakureba ko iyi ari "End-to-End Logistics & Fleet Management System" ihuza amashami yose y'ikigo cy'ubwikorezi.
* **Icyo ikemura:** Ihuza abakiriya, imodoka, abashofeli, ububiko bw'ibikoresho (inventory), imipaka (customs), na Finance muri system imwe.

---

## 2. FLEET & MAINTENANCE MODULE (Imodoka n'Igaraji)
Erekana uburyo system igenzura imodoka mbere yuko zikora urugendo.
* **Example:** Jya kuri **Vehicles** werekane imodoka `RAD 123 A` (MAN TGX). 
* **Maintenance:** Jya kuri **Maintenance Orders** werekane ko mbere yuko iyi modoka ijya Dar es Salaam, yakorewe isuzuma (Pre-trip inspection) hagahindurwa feri. Erekana *Order* `MNT-2026-001`.
* **Inventory & Suppliers:** Kanda kuri **Inventory** werekane ko system yakuye "MAN TGX Brake Pads" (`PART-001`) muri stock. Ibi bikoresho byaguzwe kuri supplier "Kigali Auto Parts Ltd". Erekana ko *Stock Movement* yabyikuyeho.

---

## 3. TRANSPORT REQUESTS & CUSTOMERS (Ubusabe bw'abakiriya)
Erekana uko akazi gatangira iyo umukiriya asabye gutwarirwa imizigo.
* **Example:** Jya kuri **Customers** werekane "Dar es Salaam Buyer Ltd".
* Jya kuri **Transport Requests** werekane ubusabe `REQ-TZ-0001` bwo gutwara amakarito 500 y'ibikoresho by'ikoranabuhanga (Electronics) kuva Kigali kugera Dar es Salaam.
* Erekana ko system yahise ibara igiciro (7,225,000 RWF) ikoresheje **Rate Card** y'uyu mukiriya mbere yuko Request yemezwa (Approved).

---

## 4. DISPATCH & TRIPS MODULE (Gupanga Urugendo)
Aha niho werekana uko imizigo ihabwa imodoka n'umushofeli.
* **Example:** Jya kuri **Trips** ufungure `TRP-TZ-0001`.
* Erekana ko iyi trip ihuje imodoka (`RAD 123 A`), umushofeli (Samuel Nkurunziza), n'imizigo (`SHP-TZ-0001`).
* **Trip Stops:** Erekana aho imodoka igomba guhagarara hose (Kigali -> Kayonza -> Rusumo -> Shinyanga -> Dar es Salaam).
* **Fuel Records:** Erekana ko i Kayonza banyweye amavuta ya 217,500 RWF (Fuel Record yuzujwe).

---

## 5. BORDER CLEARANCE & EXPENSES (Gasutamo n'Imipaka)
Erekana uburyo system ikurikirana ibibera ku mipaka.
* **Example:** Jya kuri **Border Crossings** werekane umupaka wa `Rusumo` (`CRS-TZ-2026-0001`).
* **Clearance:** Erekana ko imodoka yageze ku mupaka igakorerwa **Clearance Inspections** na Officer Mutua (Tanzania Revenue Authority), igatsinda (Passed).
* **Border Charges & Payments:** Erekana ko hishyuwe amafaranga ya gasutamo n'andi mafuti (385,000 RWF muri rusange) harimo na Clearance Payment ya 120,000 RWF.
* Byose bishyirwa muri **Expenses** z'urugendo kugira ngo zizabarwe mu gihombo n'inyungu.

---

## 6. DELIVERIES & INVOICING (Kugeza imizigo no Kwishyuza)
Imizigo yageze Dar es Salaam, ubu noneho turishyuza!
* **Example:** Jya kuri **Deliveries** werekane ko imizigo yageze kwa Hassan Juma ku gihe (`DLV-TZ-0001`).
* Jya kuri **Invoices** werekane Inyemezabuguzi `INV-TZ-0001` ingana na 9,292,500 RWF (ikubiyemo igiciro cy'urugendo, imisoro, na allowances z'umushofeli).
* **Payments:** Erekana ko umukiriya yishyuye aya mafaranga yose akoresheje Bank Transfer.

---

## 7. FINANCE & GENERAL LEDGER (Ibaraburamari / GL)
Kugira ngo wemeze aba-accountants, erekana ko system ikora ibaruramari ryikora (Automated Accounting).
* **Example:** Jya kuri **Journal Entries** ufungure Journal `JNL-2026-1001`.
* Erekana uburyo payment ikimara gukorwa, system yahise yikora *Double Entry*: 
  - **Debit:** Bank receipt - Bank of Kigali (Account 1020) - 9,292,500 RWF
  - **Credit:** Accounts Receivable reduction (Account 1100) - 9,292,500 RWF
* Ibi byerekana ko system ihuza Operations zose na Finance directly!

---

## UMWANZURO (Conclusion)
Mu gusoza presentation, vuga uti: *"Nkuko mubibona, iyi system ikurikirana ikintu cyose guhera igihe imodoka igiye mu igaraji, kugeza imizigo ipakiwe, ikambuka umupaka, kugeza amafaranga yinjiye muri Banki. Byose bikorerwa muri system imwe, bigatanga na reports zizewe!"*
