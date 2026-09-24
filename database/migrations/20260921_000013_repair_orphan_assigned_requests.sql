-- Requests marked assigned that no trip was ever created for.
--
-- "Plan trip" used to set the status on the way to the trip form. Anyone who
-- opened that form and did not save it left a request reading Assigned with
-- nothing assigned to it, and the link could never be made afterwards because
-- the trip only claimed requests still sitting at Approved.
--
-- The button no longer changes anything, and a trip now claims its request when
-- it is saved. This puts the records the old behaviour stranded back where they
-- belong: approved, and waiting for a trip.

UPDATE transport_requests
   SET status = 'approved'
 WHERE status = 'assigned'
   AND trip_id IS NULL
   AND deleted_at IS NULL;
