<?php

namespace FireflyIII\Console\Commands;

use Illuminate\Console\Command;
use FireflyIII\Models\Account;
use FireflyIII\Models\Transaction;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Models\Category;
use FireflyIII\Models\TransactionType;
use Baraveli\BMLTransaction\BML;
use Carbon\Carbon;
use FireflyIII\User;
use Illuminate\Support\Facades\DB;

class BMLSyncCommand extends Command
{
    protected $bml;


    protected $mappings = [
        "Transfer Credit" => "Deposit",
        "Transfer Debit" => "Withdrawal",
        "Purchase" => "Withdrawal"
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bml:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync bml transactions into database.';

    /**
     * Create a new command instance.  
     *        
     * @return void  
     */
    public function __construct(BML $bml)
    {
        parent::__construct();

        $this->bml = $bml->login(
            config('services.bml.username'),
            config('services.bml.password'),
            config('services.bml.account_id')
        );
    }

    /**
     * Execute the console cxommand.
     *
     * @return int
     */
    public function handle()
    {
        $todays_transactions = $this->bml->GetTodayTransactions();

        if (count($todays_transactions["history"]) > 0) {
            DB::transaction(function () use ($todays_transactions) {

                $currency = TransactionCurrency::firstOrCreate(["code" => "MVR"], [
                    "name" => "Maldivian Rufiyaa",
                    "symbol" => "MVR",
                    "decimal_places" => 2
                ]);

                collect($todays_transactions["history"])->each(function ($transaction, $key) use ($currency) {

                    $unique_transaction_hash  = hash('sha256', $transaction["description"] . $transaction["bookingDate"] . $transaction["amount"]);
                    $description = $unique_transaction_hash . " - " . $transaction["description"] . " to " . $transaction["narrative3"];

                    //Checking if amount is negative
                    if ($transaction["amount"] > 0) {
                        $amount = (float) gmp_strval(gmp_neg($transaction["amount"]));
                    } else {
                        $amount = $transaction["amount"];
                    }

                    $account = Account::first();
                    $account->transactions()->firstOrcreate(["description" => $description], [
                        "transaction_currency_id" => $currency->id,
                        "amount" => (string) $amount,
                        "transaction_journal_id" => TransactionJournal::firstOrcreate(["description" => $description], [
                            "user_id" => User::first()->id,
                            "transaction_type_id" => TransactionType::where('type', $this->mappings[$transaction["description"]])->first()->id,
                            "transaction_currency_id" => $currency->id,
                            "date" => Carbon::parse($transaction["bookingDate"]),
                            "completed" => 1,
                            "tag_count" => 0
                        ])->id
                    ]);
                });
            });
        }
    }
}
