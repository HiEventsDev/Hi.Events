#!/bin/bash

cd /Users/dave/Code/hi.events/frontend/src/locales

# Swedish (se) - Additional missing strings
cat >> se.po << 'EOF'

msgid "Active Events"
msgstr "Aktiva evenemang"

msgid "All Events"
msgstr "Alla evenemang"

msgid "All failed jobs deleted"
msgstr "Alla misslyckade jobb borttagna"

msgid "All jobs queued for retry"
msgstr "Alla jobb köade för nytt försök"

msgid "Amount Paid"
msgstr "Betalt belopp"

msgid "Are you sure you want to delete all failed jobs?"
msgstr "Är du säker på att du vill ta bort alla misslyckade jobb?"

msgid "Delete All"
msgstr "Ta bort alla"

msgid "Delete Job"
msgstr "Ta bort jobb"

msgid "Exception"
msgstr "Undantag"

msgid "Failed"
msgstr "Misslyckades"

msgid "Failed At"
msgstr "Misslyckades vid"

msgid "Failed Jobs"
msgstr "Misslyckade jobb"

msgid "Failed to delete job"
msgstr "Misslyckades med att ta bort jobb"

msgid "Failed to delete jobs"
msgstr "Misslyckades med att ta bort jobb"

msgid "Failed to retry job"
msgstr "Misslyckades med att försöka igen"

msgid "Failed to retry jobs"
msgstr "Misslyckades med att försöka igen"

msgid "Filter by Event"
msgstr "Filtrera efter evenemang"

msgid "Hi.Events Fee"
msgstr "Hi.Events-avgift"

msgid "Hi.Events platform fees and VAT breakdown by transaction"
msgstr "Hi.Events plattformsavgifter och momsfördelning per transaktion"

msgid "Important Notice"
msgstr "Viktig information"

msgid "Job"
msgstr "Jobb"

msgid "Job deleted"
msgstr "Jobb borttaget"

msgid "Job Details"
msgstr "Jobbdetaljer"

msgid "Job Name"
msgstr "Jobbnamn"

msgid "Job queued for retry"
msgstr "Jobb köat för nytt försök"

msgid "Last 14 Days"
msgstr "Senaste 14 dagarna"

msgid "Message Details"
msgstr "Meddelandedetaljer"

msgid "Monetary values are approximate totals across all currencies"
msgstr "Monetära värden är ungefärliga totaler över alla valutor"

msgid "Monitor and manage failed background jobs"
msgstr "Övervaka och hantera misslyckade bakgrundsjobb"

msgid "Most Viewed Events (Last 14 Days)"
msgstr "Mest visade evenemang (Senaste 14 dagarna)"

msgid "New Signups"
msgstr "Nya registreringar"

msgid "No failed jobs"
msgstr "Inga misslyckade jobb"

msgid "No messages found"
msgstr "Inga meddelanden hittades"

msgid "No organizer activity in the last 14 days"
msgstr "Ingen arrangörsaktivitet de senaste 14 dagarna"

msgid "No popular events in the last 14 days"
msgstr "Inga populära evenemang de senaste 14 dagarna"

msgid "No recent account signups"
msgstr "Inga nya kontoregistreringar"

msgid "No viewed events in the last 14 days"
msgstr "Inga visade evenemang de senaste 14 dagarna"

msgid "Order Holders"
msgstr "Beställningsinnehavare"

msgid "Order Ref"
msgstr "Beställningsreferens"

msgid "Orders Completed"
msgstr "Slutförda beställningar"

msgid "Orders Total"
msgstr "Totalt antal beställningar"

msgid "Outgoing Messages"
msgstr "Utgående meddelanden"

msgid "Payload"
msgstr "Payload"

msgid "Payment Date"
msgstr "Betalningsdatum"

msgid "Platform Fees Report"
msgstr "Plattformsavgiftsrapport"

msgid "Platform Revenue"
msgstr "Plattformsintäkter"

msgid "Popular Events (Last 14 Days)"
msgstr "Populära evenemang (Senaste 14 dagarna)"

msgid "Queue"
msgstr "Kö"

msgid "Recent Account Signups"
msgstr "Senaste kontoregistreringar"

msgid "Retry"
msgstr "Försök igen"

msgid "Retry All"
msgstr "Försök alla igen"

msgid "Retry Job"
msgstr "Försök jobb igen"

msgid "Search by job name or exception..."
msgstr "Sök efter jobbnamn eller undantag..."

msgid "Search by subject, event, or account..."
msgstr "Sök efter ämne, evenemang eller konto..."

msgid "Select an event"
msgstr "Välj ett evenemang"

msgid "Sent"
msgstr "Skickat"

msgid "Sent By"
msgstr "Skickat av"

msgid "Signed Up"
msgstr "Registrerad"

msgid "Stripe Payment ID"
msgstr "Stripe betalnings-ID"

msgid "Ticket Holders"
msgstr "Biljettinnehavare"

msgid "Top Organizers (Last 14 Days)"
msgstr "Topparrangörer (Senaste 14 dagarna)"

msgid "Total Fee"
msgstr "Total avgift"

msgid "Total Views"
msgstr "Totala visningar"

msgid "UUID"
msgstr "UUID"

msgid "VAT on Fee"
msgstr "Moms på avgift"

msgid "VAT Rate"
msgstr "Momssats"

msgid "View all messages sent across the platform"
msgstr "Visa alla meddelanden skickade på plattformen"

msgid "View Message"
msgstr "Visa meddelande"
EOF

echo "Swedish translations added successfully!"
