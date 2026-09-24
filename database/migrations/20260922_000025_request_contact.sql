-- Who asked, as opposed to who typed it in.
--
-- "Requested by" held the member of staff signed in when the request was
-- entered, which is an audit fact and not an answer to "who wants this?". On a
-- customer's request it read as though our own dispatcher had ordered the load.
--
-- The two are separated: the staff member is Logged by, and the person at the
-- customer who actually asked gets their own field. A customer has more than one
-- buyer, and the one who rang is the one to ring back.
ALTER TABLE transport_requests
    ADD COLUMN IF NOT EXISTS requested_by_contact VARCHAR(150) NULL AFTER customer_id;

-- Where the customer has a contact on file, start from that name rather than
-- leaving the field blank on every request already raised.
UPDATE transport_requests r
  INNER JOIN customers c ON c.id = r.customer_id
    SET r.requested_by_contact = c.contact_name
  WHERE r.requested_by_contact IS NULL
    AND c.contact_name IS NOT NULL
    AND c.contact_name <> '';
