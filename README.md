# Xtrusio Amazon SES Plugin

Amazon SES webhook handler plugin for **Xtrusio 7+ (Mautic 6+)**. Automatically tracks bounces, complaints, deliveries, and more via AWS SNS webhooks. Marks contacts as **Do Not Contact** on hard bounces and spam complaints.

Works with Symfony's built-in SES transport (`symfony/amazon-mailer`) — no custom transport needed.

## Features

- Full support for **all 10 SES event types** (Bounce, Complaint, Delivery, Reject, Send, Open, Click, DeliveryDelay, Rendering Failure, Subscription)
- **SES v1 + v2** payload format support
- Email address parsing for SES `"Display Name" <email>` format
- SNS `SubscriptionConfirmation` auto-confirm
- SNS `UnsubscribeConfirmation` handling
- Modern PHP 8.2+ strict typing

---

## Requirements

- Xtrusio 7.x (or Mautic 7.x / 6.x)
- PHP 8.2+
- `symfony/amazon-mailer` package
- AWS account with SES and SNS access
- SES verified domain or email address

## Installation

```bash
# 1. Install the Symfony SES mailer package
composer require symfony/amazon-mailer

# 2. Copy plugin to plugins directory
cp -r XtrusioAmazonSesBundle /path/to/xtrusio/plugins/

# 3. Clear cache and reload plugins
php bin/console cache:clear
php bin/console xtrusio:plugins:reload
```

Or use the UI: **Settings > Plugins > "Install/Upgrade Plugins"** after copying files.

## Quick Start Guide

### Step 1: Create IAM Access Keys

1. Go to **AWS Console > IAM > Users**
2. Select or create a user with SES permissions
3. Go to **Security credentials > Create access key**
4. Save the **Access Key ID** and **Secret Access Key**

### Step 2: Configure Xtrusio Email Settings

Go to **Xtrusio > Settings > Email Settings** and configure:

| Field | Value |
|---|---|
| **Scheme** | `ses+api` |
| **Host** | `default` |
| **User** | Your AWS Access Key ID (e.g. `AKIAIOSFODNN7EXAMPLE`) |
| **Password** | Your AWS Secret Access Key |
| **Options** | Key: `region`, Value: your AWS region (e.g. `us-east-1`, `eu-west-1`, `ap-south-1`) |

Click **Save** and use the **"Send Test Email"** button to verify.

### Step 3: Create SNS Topic

1. Go to **AWS SNS Console > Topics > Create topic**
2. Select **Standard** type
3. Name it `xtrusio-ses-notifications`
4. Click **Create topic**

### Step 4: Create SNS Subscription

1. Inside the topic, click **Create subscription**
2. Set:
   - **Protocol**: HTTPS
   - **Endpoint**: `https://your-domain.com/mailer/callback`
3. Click **Create subscription**
4. The plugin **auto-confirms** the subscription

### Step 5: Connect SES to SNS

1. Go to **SES > Identities > your-domain > Notifications tab**
2. Click **Edit** on **Feedback notifications**
3. Set your SNS topic for **Bounce**, **Complaint**, and **Delivery**
4. Enable **Include original email headers**
5. Click **Save**

### Step 6: Test It!

1. Create a contact in Xtrusio with email: `bounce@simulator.amazonses.com`
2. Send an email to that contact
3. The contact should be marked as **Do Not Contact (Bounced)**

## Alternative Configuration Methods

### Via config/local.php

```php
'mailer_dsn' => 'ses+api://ACCESS_KEY:SECRET_KEY@default?region=us-east-1',
```

### Via Environment Variable

```bash
MAILER_DSN=ses+api://ACCESS_KEY:SECRET_KEY@default?region=us-east-1
```

## Available DSN Schemes

| Scheme | Protocol | Use case |
|---|---|---|
| `ses+api` | SES v2 HTTPS API | **Recommended** - fastest |
| `ses+https` | SES v2 HTTPS | Alternative API method |
| `ses+smtp` | SMTP via SES | Use if API access is restricted |
| `ses` | Default (same as `ses+smtp`) | Fallback |

## Supported SES Event Types

All 10 SES event types are handled:

| Event Type | Action | Description |
|---|---|---|
| **Bounce** | Marks contact as **Do Not Contact (Bounced)** | Hard/soft bounce with full diagnostics |
| **Complaint** | Marks contact as **Do Not Contact (Unsubscribed)** | Spam complaint from recipient |
| **Delivery** | Logged | Successful delivery confirmation |
| **Reject** | Marks contact as **Do Not Contact (Bounced)** | SES rejected the email (e.g., virus detected) |
| **Send** | Logged | SES accepted the email for delivery |
| **Open** | Logged | Recipient opened the email |
| **Click** | Logged | Recipient clicked a link |
| **DeliveryDelay** | Logged as warning | Temporary delay (mailbox full, etc.) |
| **Rendering Failure** | Logged as error | SES template rendering failed |
| **Subscription** | Logged | Recipient changed subscription preferences |

## SNS Message Types

| Type | Action |
|---|---|
| **SubscriptionConfirmation** | Auto-confirmed (calls SubscribeURL) |
| **UnsubscribeConfirmation** | Logged as warning |
| **Notification** | Unwrapped and processed as SES event |

Supports both **SES v1** (`notificationType`) and **SES v2** (`eventType`) payload formats.

## Advanced: Configuration Set (Optional)

For tracking Open, Click, and other v2 events, create a Configuration Set:

1. Go to **SES > Configuration Sets > Create**
2. Name it `xtrusio-tracking`
3. Add an **SNS event destination** for all event types
4. Add `configuration_set=xtrusio-tracking` to your DSN options

## Webhook Endpoint

```
POST /mailer/callback
```

Accepts:
- SNS SubscriptionConfirmation (auto-confirmed)
- SNS UnsubscribeConfirmation (acknowledged)
- SNS Notification wrapping SES events
- Direct SES event JSON

## SES Simulator Addresses (For Testing)

| Address | Simulates |
|---|---|
| `bounce@simulator.amazonses.com` | Hard bounce |
| `complaint@simulator.amazonses.com` | Spam complaint |
| `success@simulator.amazonses.com` | Successful delivery |
| `suppressionlist@simulator.amazonses.com` | Suppression list bounce |
| `ooto@simulator.amazonses.com` | Out of office (soft bounce) |

## Troubleshooting

### "Unsupported scheme ses+api" error
```bash
composer require symfony/amazon-mailer
```

### Bounces not being recorded
- Verify SNS subscription is **Confirmed** in AWS SNS Console
- Check SES Notifications tab - is your SNS topic selected for Bounce/Complaint/Delivery?
- Check webhook URL is publicly accessible
- Check Xtrusio logs: `var/logs/`

### Test email works but campaign emails don't
- Check SES sending limits in AWS Console
- Verify you're out of the SES sandbox (or recipients are verified)

### Plugin not showing in Xtrusio
```bash
php bin/console cache:clear
php bin/console xtrusio:plugins:reload
```

## IAM Policy

Minimum required IAM permissions for sending:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "ses:SendEmail",
                "ses:SendRawEmail",
                "ses:GetSendStatistics",
                "ses:GetSendQuota"
            ],
            "Resource": "*"
        }
    ]
}
```

## License

GPL-3.0-or-later
