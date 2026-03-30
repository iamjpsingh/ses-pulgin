# Xtrusio Amazon SES Plugin

Adds bounce, complaint, and delivery tracking for Amazon SES via SNS webhooks. Works with Symfony's built-in SES transport (`symfony/amazon-mailer`).

## Requirements

- Xtrusio 7.x (or Mautic 7.x)
- PHP 8.2+
- `symfony/amazon-mailer` package installed
- AWS account with SES access
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

## Configuration via UI

Go to **Settings > Email Settings > Email DSN** and fill in:

| Field | Value |
|---|---|
| **Scheme** | `ses+api` |
| **Host** | `default` |
| **User** | Your AWS Access Key ID (e.g. `AKIAIOSFODNN7EXAMPLE`) |
| **Password** | Your AWS Secret Access Key |
| **Options** | Key: `region`, Value: your AWS region (e.g. `us-east-1`, `eu-west-1`, `ap-south-1`) |

Click **Save** and use the **"Send Test Email"** button to verify.

## Configuration via config/local.php

```php
'mailer_dsn' => 'ses+api://ACCESS_KEY:SECRET_KEY@default?region=us-east-1',
```

## Configuration via Environment Variable

```bash
MAILER_DSN=ses+api://ACCESS_KEY:SECRET_KEY@default?region=us-east-1
```

## Available DSN Schemes

| Scheme | Protocol | Use case |
|---|---|---|
| `ses+api` | SES v2 HTTPS API | Recommended - fastest |
| `ses+https` | SES v2 HTTPS | Alternative API method |
| `ses+smtp` | SMTP via SES | Use if API access is restricted |
| `ses` | Default (same as `ses+smtp`) | Fallback |

## AWS Setup for Bounce/Complaint Tracking

### Step 1: Create SNS Topic

1. Go to **AWS SNS Console**
2. Create a new topic (e.g., `xtrusio-ses-notifications`)
3. Note the Topic ARN

### Step 2: Subscribe to SNS Topic

Create an HTTPS subscription pointing to your Xtrusio webhook:

```
https://your-domain.com/mailer/callback
```

The plugin automatically confirms the SNS subscription.

### Step 3: Configure SES Notifications

1. Go to **AWS SES Console > Verified Identities**
2. Select your verified domain/email
3. Go to **Notifications** tab
4. Set **Bounce**, **Complaint**, and **Delivery** notifications to your SNS topic

### Step 4: (Recommended) Create Configuration Set

1. Go to **SES > Configuration Sets**
2. Create a new set (e.g., `xtrusio-tracking`)
3. Add an **SNS event destination** for Bounce, Complaint, Delivery, and Reject events
4. Add `configuration_set` to your DSN options in the UI

## How It Works

### Sending
Handled by Symfony's built-in `SesTransportFactory` from the `symfony/amazon-mailer` package.

### Bounce Handling (this plugin)
- Hard bounces (Permanent) mark contacts as **Do Not Contact (Bounced)**
- Soft bounces (Transient) are logged for monitoring
- Bounce details stored in email stats

### Complaint Handling (this plugin)
- Spam complaints mark contacts as **Do Not Contact (Unsubscribed)**
- Complaint type (abuse, auth-failure, etc.) logged

### Delivery Tracking (this plugin)
- Successful deliveries are logged for audit purposes

## Webhook Endpoint

```
POST /mailer/callback
```

Accepts:
- SNS SubscriptionConfirmation (auto-confirmed)
- SNS Notification wrapping SES events
- Direct SES event JSON

## Troubleshooting

### "Unsupported scheme ses+api" error
Run: `composer require symfony/amazon-mailer`

### Test email works but campaign sending doesn't
- Check SES sending limits in AWS console
- Verify you're out of the SES sandbox (or recipients are verified)

### Bounces not being recorded
- Verify SNS subscription is confirmed (check AWS SNS console)
- Check that the webhook URL is publicly accessible
- Check Xtrusio logs at `var/logs/`

## IAM Policy

Minimum required IAM permissions:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "ses:SendEmail",
                "ses:SendRawEmail"
            ],
            "Resource": "*"
        }
    ]
}
```

## License

GPL-3.0-or-later
