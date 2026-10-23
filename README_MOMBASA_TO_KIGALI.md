# Hands-on guide: cargo from Mombasa to Kigali

This guide follows one customer consignment through the LMS using the normal
pages and buttons. Enter the records yourself; **do not run seed files, test
scripts, or SQL inserts for this exercise**.

It is written for a road move where the goods are already in Mombasa and need
to reach a customer in Kigali. It does not include an ocean-freight leg.

> **Practice safely:** use a training/test installation first. If you use the
> live system, enter only real customer, vehicle, customs, tax, and payment
> information. Names, addresses, weights, and prices below are examples.
> Customs requirements and amounts must come from your licensed clearing
> agent and the relevant authorities; this guide is not customs or tax advice.

## The example consignment

Use this sample to understand what belongs in each field. Replace it with the
actual paperwork and agreed terms for your own customer.

| Item | Example |
| --- | --- |
| Customer | Kigali Hardware Ltd |
| Customer contact | Eric Mugisha |
| Phone / email | `+250 788 123 456` / `orders@example.test` |
| Collection | Customer's warehouse or CFS in Mombasa (enter the exact address) |
| Delivery | Kigali Hardware Ltd, Gikondo, Kigali (confirm the exact address) |
| Cargo | One 40 ft container of building materials |
| Weight | 24,000 kg (replace with the verified gross weight) |
| Container / seal | `MSKU7788123` / `ML-4471209` (illustration only) |
| Delivery contact | Eric Mugisha |
| Price | Enter the price accepted by the customer; do not assume the example is a tariff |

The references above are examples, **not real customs, container, or payment
references**. Use the numbers printed on the actual documents.

## Who signs in and what they do

Use each person's own active LMS account. The job title below describes the
role needed; sign in with the actual user account for that role in your
company. Never share passwords.

| Login as | Work in this example |
| --- | --- |
| Logistics / Operations Officer — Mombasa | Register the customer request, quote it, open the freight job and shipment, plan the trip, and follow delivery |
| Fleet Manager | Confirm the vehicle, driver, licences, insurance, inspection, and cross-border permits |
| Clearance Officer — Kenya | Prepare and process the Kenyan transit file |
| Clearance Officer — Rwanda | Prepare and process the Rwanda import file |
| Separate Operations Manager / authorized approver | Review and approve the trip release |
| Driver | See the assigned trip, start it, update stops, and record handover |
| Finance Officer | Record customs payments, expenses, invoice, and customer payment |

If an account or menu is missing, ask your LMS administrator to create or
authorize it in **Administration → Users** and **Administration → Role
permissions**. The page search at the top of the LMS can find a page by its
name. The sidebar only shows pages that the signed-in role is allowed to use.

## Before opening the job

1. Confirm that the customer exists. If not, sign in as Operations and open
   **Commercial → Customers → Add customer**. Record the customer's legal
   name, contact person, phone, email, billing currency, and payment terms.
2. Confirm that a current transport rate exists in **Commercial → Rates**.
   Use the actual Mombasa-to-Kigali rate and currency approved by your company.
   If no rate exists, ask the authorized person to enter and approve one; do not
   make up a price just to get past the form.
3. Ask Fleet to confirm that the truck and driver records are current:
   **Fleet management → Vehicles**, **Drivers**, **Vehicle documents**, and
   **Driver documents**. Check capacity, insurance, inspection, licence, and
   all permits required for the planned route.
4. Collect the customer's written order and cargo papers: for example, the
   commercial invoice, packing list, bill of lading or delivery order if
   applicable, container/seal details, and the actual customs documents.
   Upload scans or PDFs to the relevant record when prompted.

## The job, page by page

### 1. Operations records the transport request

Sign in as the **Logistics / Operations Officer**.

There are two ways for the request to enter:

- If the customer submits it, they use the public **Request transport** page
  (`/request-transport`). Operations then opens **Transport → Transport
  requests** and finds the new request.
- If the customer contacts the office, open **Transport → Transport
  requests → New request** and enter it for them.

For this example, enter:

- **Customer:** Kigali Hardware Ltd
- **Asked for by:** Eric Mugisha
- **Service asked for:** Road transport
- **Type of load:** FCL (full container)
- **Pickup location:** exact warehouse/CFS address in Mombasa
- **Destination:** Kigali Hardware Ltd, Gikondo, Kigali
- **Required date:** the date agreed with the customer
- **Cargo description:** one 40 ft container of building materials
- **Estimated weight:** 24,000 kg, after checking the customer's papers
- **Priority:** the real agreed priority
- **Customer order number / LPO:** enter it if the customer supplied one

Save the request. The system creates its reference, for example `REQ-...`.
Write down that reference; later pages link to it. Do not type a status into a
status field if the page provides **Quote**, **Approve**, or another workflow
button.

### 2. Operations quotes and records customer acceptance

Open the new request. Check the cargo, dates, route, customer contact, and the
current rate card. Press **Quote the customer** (or the quote action shown on
that request) and enter or confirm the amount and currency from your approved
rate.

Send the quote to the customer using your normal company process. When the
customer has accepted, record that acceptance on the request using
**Customer accepted / Approve request**. If the LMS routes the request to
**Approvals**, the designated approver signs it there instead.

**Expected result:** the request shows as approved. If the customer declines,
use the reject action and record the reason; do not create a trip for an
unaccepted quote.

### 3. Operations opens the freight job and shipment

Go to **Transport → Freight jobs → Open freight job**. Link the approved
request and enter the road leg:

- **Customer / From request:** Kigali Hardware Ltd and the request reference
- **Direction:** Import (the cargo is entering Rwanda)
- **By:** Road
- **Our job:** Door to door, if ERI is responsible for collection through
  delivery; otherwise select the scope actually sold
- **Load type:** FCL (full container)
- **Cargo:** building materials
- **Packages, weight, containers:** use the cargo documents
- **Origin:** the exact Mombasa collection point
- **Final destination:** the confirmed Kigali delivery address
- **Agreed price / currency:** the accepted quote
- **Carrier:** select the actual carrier or haulier if the field applies
- **Notes:** record handling instructions that the team needs

For a road-only move, do not invent a vessel, voyage, port-of-loading, or
bill-of-lading detail. If ERI is also responsible for the sea-freight arrival
in Mombasa, record the real sea-freight leg and its documents instead.

Save the freight job. The system opens a linked **Shipment**. Follow the
**Open the shipment** link and check or complete:

- Consignee name and phone
- Container size, container number, seal number, and trailer number if known
- Cargo description, quantity, gross weight, declared value, and currency
- Delivery address and customer order number
- Shipment documents: upload the actual invoice, packing list, transport
  document, delivery order, and any other papers requested for this shipment

Use the **Verify paper** action only after checking that the uploaded paper is
legible and its number agrees with the original. If it is wrong or missing,
use **Send back** and explain what must be corrected.

### 4. Clearance prepares the border files

Do this with the actual clearing agent and official paperwork. The route in
this example is Mombasa → Malaba → Uganda → Gatuna → Kigali. Confirm the
route and all required transit/import procedures before dispatch. The LMS
stores the team's records; it does not issue customs permits or calculate
official duties.

#### Kenya — Mombasa / transit out

Sign in as **Clearance Officer — Kenya**. Open **Clearance → Customs
clearance → Start clearance** and create the Kenya file linked to the shipment.
Select the correct border post / customs office and **Transit** direction.

Add the declaration number and the actual supporting references. Depending on
the consignment and current rules, documents may include a customs
declaration, transit bond, electronic cargo tracking seal, commercial
invoice, packing list, transport document, and delivery order. Upload each
real document and press **Verify paper** after checking it.

Use **Submit declaration** only after the clearing agent has actually lodged
the declaration and supplied its official reference. At the border, use the
clearance workflow to **Start review**, **Assess duties**, record/request any
payment, and mark the file **Cleared** and **Released** only when the official
release and receipt are in hand.

#### Rwanda — Gatuna / import

Sign in as **Clearance Officer — Rwanda**. Create a second **Customs
clearance** file for the same shipment, selecting the correct Rwanda border
post, **Rwanda**, and **Import** direction. Link the trip, vehicle, and driver
once those records exist.

Upload and verify the documents requested by the Rwanda clearing agent.
Enter the real import declaration number and submit it at the time directed by
the agent. When the assessment is available, record each actual duty, VAT,
and agent charge in the clearance charge lines, using the correct currency
and indicating who is responsible for payment. Do not copy the example
amounts or references from any demo data.

Finance records payment against the official assessment and enters the
receipt/reference. Clearance verifies the receipt, then marks the file
**Cleared** and **Released** when customs has released the cargo.

> If the actual route requires a separate Uganda transit filing or any other
> permit, have the clearing agent tell you what to record. Do not treat a
> checkpoint as a customs release, and do not mark a file released before the
> authority has released it.

### 5. Fleet and Operations prepare the truck and trip

The **Fleet Manager** checks **Vehicles**, **Drivers**, and their documents.
If the truck/driver does not exist, create them from those pages using the
actual registration, licence, capacity, expiry dates, insurance, inspection,
and permits. Do not reuse another vehicle's or driver's record.

The **Operations Officer** opens **Transport → Trips → Create trip**:

- **Fulfils request:** select the approved request
- **Customer:** Kigali Hardware Ltd
- **Trip type:** Delivery
- **Pickup location:** exact Mombasa collection point
- **Final destination:** exact Kigali address
- **Cargo summary:** one 40 ft container of building materials, verified weight
- **Vehicle / Driver:** the checked, available truck and assigned driver
- **Planned departure / arrival:** the agreed operational plan
- **Status:** leave the system's initial status; do not force it to In transit

Save the trip. On its detail page, add **Stops on this route** in order, for
example:

1. Pickup — Mombasa CFS / warehouse
2. Checkpoint — Malaba border
3. Waypoint — Kampala, if that is the actual planned stop
4. Checkpoint — Gatuna border
5. Drop-off — Kigali Hardware Ltd, Gikondo; add Eric's real phone number

Link the shipment to the trip (from the shipment's **Edit** page or the
available link on the trip/shipment page). Create a **Delivery** linked to
this trip and shipment, and confirm the recipient name, phone, address, and
planned delivery time.

### 6. Complete checks and get approvals before departure

Operations opens **Pre-dispatch checks → Run check** and selects the shipment.
Confirm that cargo is loaded and the quantity, packaging, route, vehicle,
driver, and papers are correct. Save the check as ready only if each item has
actually passed.

Now open **Approvals** and the trip:

1. The assigned **Clearance Officer** checks the clearance files and required
   papers, then approves the clearance-ready step.
2. A **different authorized Operations Manager** reviews the job and approves
   release of the truck.
3. Operations checks that the trip is approved and the pre-dispatch check is
   ready.

The person who created/submitted an approval must not sign their own approval
when the system requires another approver. If the trip is held, read the
message, fix the missing document or record, and resubmit. Do not bypass the
hold by changing a status manually.

### 7. Driver runs the trip and reports the handover

The **Driver** signs in and opens **My trips**. They should see only their
assigned work. Open the approved trip and press **Start trip** when the truck
actually departs. Enter the actual odometer reading if requested.

At each saved stop:

- Press **Arrive** when the truck physically reaches it.
- Complete the border/customs actions with the clearance officer; the driver
  must not mark a customs file released on the officer's behalf.
- Press **Leave** when the truck physically departs.
- Record fuel or a trip expense only from the actual receipt, in the currency
  printed on it.

At the Kigali destination, the driver confirms the recipient's identity and
condition/quantity of cargo. On the linked **Delivery** record, enter who
received the cargo, their ID if required by company policy, and upload the
signed delivery note/photo and recipient signature. Then press **Collected /
delivered**. If delivery fails, press **Record failure**, give the true reason,
and reschedule; do not mark it delivered.

### 8. Operations confirms delivery; Finance closes the money

Operations opens the trip and presses **Mark delivered** after checking the
proof of delivery. Confirm that the shipment and delivery show **Delivered**.
Then use the freight job action **Delivered** and **Close the job** when all
work and paperwork are complete.

Finance then:

1. Reviews the trip, approved quote, clearance charges, and supporting
   receipts.
2. Creates the invoice from the trip using the **Create invoice** action.
   Check customer, line items, currency, taxes, and payment terms before
   issuing it.
3. If another authorized person must approve the invoice, that person opens
   **Approvals** and issues it.
4. Records customer payment from the actual bank/cash receipt and applies it
   to the correct invoice.
5. Reconciles bank transactions and reviews the relevant reports/books.

Never enter one currency's amount as another currency. Keep official customs
payments in the currency actually paid and attach the supporting receipt.

## What to do when something is wrong

- **Create / insert:** use the page's **New**, **Add**, **Open**, or **Create**
  button. Choose linked customer, request, shipment, trip, and clearance
  records rather than typing duplicate names when a selector is available.
- **Correct:** open the record, choose **Edit**, correct the source information,
  and save. Add a note if the correction affects other teams.
- **Approve:** use the specific workflow button or **Approvals** inbox. Verify
  the papers and facts first. An approval is an accountable signature, not
  just a way to move a badge to green.
- **Reject / send back:** choose the relevant action and give a clear reason
  describing the missing or incorrect item.
- **Delete:** only remove a duplicate or accidental record before it has
  become part of an active trip, clearance, delivery, invoice, or audit trail.
  Use the page's Delete control and provide the reason if requested. Some
  records cannot be deleted. For active work, use **Cancel** or **Reject**
  with a reason instead; do not delete the history.
- **Do not edit workflow status by hand.** Use the buttons such as **Quote the
  customer**, **Submit declaration**, **Mark cleared**, **Approve trip**,
  **Start trip**, **Mark delivered**, or **Issue invoice**. The system may
  prevent the next step until required papers, payments, or approvals exist.

## Final checklist

Before telling the customer the job is finished, verify:

- The request was accepted and the agreed price matches the quote.
- The freight job, shipment, trip, border files, and delivery are linked.
- Cargo, container, seal, customer, and consignee details match the source
  documents.
- All customs documents, releases, duty/tax payments, and receipts are real,
  verified, and attached to the correct border file.
- The vehicle and driver's documents were valid for the route and dates.
- The driver recorded the actual stops and delivery evidence.
- The trip, shipment, delivery, and freight job show their completed statuses.
- Finance issued the correct invoice and recorded payments against it.
- The customer receives the delivery note and tracking reference. They can use
  the public **Track** page (`/track`) if tracking is enabled for that
  reference.

