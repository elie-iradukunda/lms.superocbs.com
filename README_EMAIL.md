# Email in LMS

Written for: whoever runs this installation.

Every update the bell shows is also sent by email, through
[Resend](https://resend.com). The bell reaches someone already looking at the
screen; email reaches the driver on the road and the accountant who has not
signed in today.

---

## What gets emailed

| When this happens | Who is written to |
| --- | --- |
| A transport request is approved | The logistics team |
| A transport request is rejected | Whoever raised it |
| A trip is dispatched | The driver on it |
| A trip is completed | The logistics team |
| A delivery is completed or fails | The logistics team |
| An expense is approved or rejected | Whoever submitted it |
| A purchase request is approved | The warehouse team |
| Goods are received | The warehouse team |
| An invoice is issued | The finance team |
| A licence or vehicle document is expiring | The fleet team |
| A service falls due | The fleet team |
| Stock drops below its minimum | The warehouse team |
| An invoice goes overdue | The finance team |
| **Someone asks to reset a password** | That person, with the single-use link |
| **A new account is created** | That person, with their one-time password |

The last two are the important ones: before this, the reset link and the
one-time password were printed on the administrator's screen and had to be read
out. Now they go straight to the person, and only fall back to the screen when
the email could not be sent.

---

## Setting it up

Put these in `.env` at the project root. That file is ignored by git, so the key
never reaches the repository.

```text
RESEND_API_KEY=re_xxxxxxxxxxxxxxxxxxxx
SMTP_FROM_EMAIL=lms@updates.itec.rw
SMTP_FROM_NAME=ITEC Ltd
INITIAL_REPLY_TO=someone@yourcompany.rw

# Links inside an email cannot be relative.
APP_URL=http://localhost/logistics-mvc

# While testing: send everything to one mailbox instead of to real people.
# Clear it for production.
MAIL_REDIRECT_ALL_TO=you@yourcompany.rw
```

`SMTP_FROM_EMAIL` must be on a domain you have verified in Resend, or the
provider refuses the message and the outbox records why.

Copy `.env.example` if you are starting fresh.

### Check it works

Administration → **Email outbox** → **Send a test to myself**.

**Expect:** a green bar, and the message in the list with a green **Sent** badge
and a provider id. If it says Failed, the reason is on the row.

---

## The outbox

Administration → **Email outbox** holds every message the system meant to send,
whether it went or not.

A message is **written down first and only then handed to the provider**. If
mail is off, if the key is missing, if Resend is unreachable, the message is
still on record with the reason it did not go. Nothing is quietly lost, and
nobody has to wonder whether the driver was actually told.

| Status | What it means |
| --- | --- |
| **Sent** | The provider accepted it and returned an id. |
| **Queued** | Not sent yet, or the provider could not be reached. It will go on the next flush. |
| **Failed** | The provider refused it — a bad address, an unverified domain. The reason is on the row. |
| **Not sent** | Email is switched off, or it was raised from the console. |

Open any message to see it exactly as it arrives, its plain-text version, and
its delivery history. **Send it now** retries one; **Send everything waiting**
retries the lot.

---

## Controlling it

### For the company — Company settings → Email

| Setting | What it does |
| --- | --- |
| Send email notifications | The master switch. Off means nothing is queued at all. |
| Signature | The line at the foot of every message. |
| Least important severity to email | `info` sends everything. Raise it to `warning` or `danger` to email only what matters and leave the rest to the bell. |
| Email the operational alerts | Whether expiring documents, low stock and overdue invoices go out by email as well. |

### For one person — My profile

A switch: **Send me an email as well as the on-screen bell**. Turn it off and
that person still gets every update on the bell; only the email stops.

Password resets are always sent, because someone who cannot sign in cannot read
the bell.

---

## Rules the system keeps

- **One message per person, once.** Every message carries a key. The same alert
  raised twice is recorded and sent once. A notification to a role of six people
  becomes six letters, not one letter six times and not one with six addresses.
- **Nobody who should not get it, does.** Inactive accounts, deleted accounts and
  anyone who has opted out are left out.
- **The console never mails real people.** A test run or a migration records its
  messages and marks them *Not sent*. Only a real browser request sends. Set
  `MAIL_SEND_IN_CLI=1` to override that deliberately.
- **Email never breaks the work.** Sending is the last thing that happens after
  an approval or a dispatch. If it fails, the approval still stands and the
  failure is on the outbox row.
- **Three attempts, then it stops.** A message that has failed three times is
  marked Failed rather than retried forever. An administrator can still push it
  again by hand.

---

## Before going live

- **Rotate the API key** if it has ever been pasted into a chat, an email or a
  screenshot. Resend lets you revoke and reissue in one click.
- **Clear `MAIL_REDIRECT_ALL_TO`**, or every message keeps going to one mailbox.
- **Set `APP_URL`** to the real address. Links in an email are useless otherwise.
- **Verify the sending domain** in Resend, and add its SPF and DKIM records, or
  messages land in spam.
- **Check `.env` is not in git**: `git check-ignore -v .env` should name the rule.

---

## When something does not arrive

1. Administration → **Email outbox**, and filter by **Failed**. The reason is on
   the row.
2. No row at all? Either email is switched off, the severity threshold is above
   that message, or the recipient has opted out.
3. Row says **Sent** but nothing arrived? Look in spam, then at Resend's own
   dashboard using the provider id on the message.
4. `tests/email.php` covers the whole path and names the assertion that broke.
