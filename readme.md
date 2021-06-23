FireFly Fork That adds BML Transactions Auto Sync Support.

https://github.com/firefly-iii/firefly-iii/


## Usage

Add the following variables to `.env` and fill according to your credentials.
```
BML_USERNAME=
BML_PASSWORD=
BML_ACCOUNT_ID= // If you have multiple accounts
```

Command To sync the transactions from bml to firefly. This command will automatically ignore any transactions that have already been synced to the database.

```
php artisan bml:sync
```

## Cron

If you want to automate the sync process from bml into firefly. Add update and add the following code to your `crontab` file. This will sync your transactions from bml into firefly every 12 hours.

```
0 */12 * * * cd /path-to-your-project && php artisan bml:sync >> /dev/null 2>&1
```
