- Validation on order finalise
- Don't return hidden tickets in the public questions response. Or don't return tickets at all
- Docker prod image
- Digital Ocean on click setup
- Sorting - allow desc/asc
- Move mutations into their own files
- PDF design
- Email copy and design
- Switch to bun.sh
- confirm all writes are in a transaction
- handle 404 on the ticket widget
- update useGetEvent to handle errors / 404s
- T&Cs on login page
- Allow automated sale start based on previous tier selling out

## Tables to delete

- account_users
- order_discount_codes
- customers
- attribute_event
- ticket_attributes
- order_attributes

P1
- Manually check payment intent in polling fails - Done
- Deleting questions - Done
- Stripe connect - 90% done
- Homepage charts - 95% done
- Event dashboard - Done
- Organizer - Done
- Sorting tickets and questions - Done
- Promo code discount type none bug - Done
- Hidden tickets - Done
- live event toggle - Done
- UTC all dates - Done
- Ticket status ticket list - Done
- getQuantityRemaining refactor - Done
- Confirm email on register - Done
- PDF download - Done
- Email individual tickets + resend email - Done
- Export Question Answers - Done
- Organizer order notifications - Done
- Cancel order modal cancel button doesn't work - Done
- Event Messaging boxes - Done
- Hide draft event from public - Done
- View attendee details modal - Done
- Min/Max quantity on tickets - Done
- Tooltips for mobile - Done

- Confirm order quantities calculations -done
- Fix attendee question errors - done
- Ensure email links are working - done

- Licence table 
- Hide Payment Tab - done 
- Resend confirmation email
- remove reply-to from emails
- SAAS mode
- update docs
- CTA to buy licence in app
- refactor 403 vs 401s - 403s should not redirect - they should show a message

.hi-promo-code-row  - check css is nested correctly

P3
- Organizer social handles and meta
- Multiple requests being made when query param are present
- Changing a question type could break exports

# Set up
docker-compose up -d
## set up the database
docker-compose exec backend php artisan migrate