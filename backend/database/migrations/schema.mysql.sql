-- DO NOT MODIFY THIS FILE. Create a new migration file instead.

create table if not exists migrations
(
    id        bigint unsigned auto_increment primary key,
    migration varchar(255) not null,
    batch     integer      not null
);

create table if not exists personal_access_tokens
(
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type varchar(255) not null,
    tokenable_id   BIGINT UNSIGNED       not null,
    name           varchar(255) not null,
    token          varchar(64)  not null,
    abilities      text,
    last_used_at   timestamp(0),
    expires_at     timestamp(0),
    created_at     timestamp(0),
    updated_at     timestamp(0),
    constraint personal_access_tokens_token_unique
        unique (token)
);

create index personal_access_tokens_tokenable_type_tokenable_id_index
    on personal_access_tokens (tokenable_type, tokenable_id);

create table if not exists password_reset_tokens
(
    email      varchar(255) not null,
    token      varchar(255) not null,
    created_at timestamp,
    deleted_at timestamp,
    updated_at timestamp,
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
);

create index password_reset_tokens_email_index
    on password_reset_tokens (email);

create index password_reset_tokens_token_index
    on password_reset_tokens (token);

create table if not exists failed_jobs
(
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid       varchar(255)                           not null,
    connection text                                   not null,
    queue      text                                   not null,
    payload    text                                   not null,
    exception  text                                   not null,
    failed_at  timestamp(0) default CURRENT_TIMESTAMP not null,
    constraint failed_jobs_uuid_unique
        unique (uuid)
);

create table if not exists accounts
(
    id                            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    currency_code                 varchar(3) not null default 'USD',
    timezone                      varchar(255),
    created_at                    timestamp,
    updated_at                    timestamp,
    deleted_at                    timestamp,
    name                          TEXT                                    not null,
    email                         TEXT                                    not null,
    stripe_account_id             varchar(50),
    short_id                      varchar(20)                                 not null,
    stripe_connect_setup_complete boolean    default false,
    account_verified_at           timestamp
);

create table if not exists password_resets
(
    email      varchar(200),
    token      varchar(200),
    created_at timestamp,
    updated_at timestamp,
    deleted_at timestamp,
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address TEXT not null,
    user_agent TEXT
);

create table if not exists timezones
(
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       varchar(64) not null,
    deleted_at timestamp
);

create table if not exists roles
(
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        TEXT,
    permissions JSON not null,
    account_id  BIGINT UNSIGNED,
    constraint roles_accounts_id_fk
        foreign key (account_id) references accounts(id)
);

create table if not exists users
(
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email             varchar(255) not null,
    email_verified_at timestamp(0),
    password          varchar(255) not null,
    remember_token    varchar(100),
    created_at        timestamp(0),
    updated_at        timestamp(0),
    deleted_at        timestamp,
    first_name        TEXT     not null,
    last_name         TEXT,
    pending_email     TEXT,
    timezone          TEXT     not null
);

create table if not exists event_logs
(
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED       not null,
    type        varchar(255) not null,
    entity_id   BIGINT UNSIGNED       not null,
    entity_type BIGINT UNSIGNED       not null,
    ip_address  TEXT,
    user_agent  TEXT,
    data        JSON,
    created_at  timestamp    not null,
    updated_at  timestamp,
    deleted_at  timestamp,
    
    constraint event_logs_users_id_fk
        foreign key (user_id) references users(id)
);

create table if not exists taxes_and_fees
(
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name             varchar(255)          not null,
    calculation_type varchar(20)           not null,
    rate             DECIMAL(10, 3)        not null,
    is_active        boolean default true,
    description      text,
    created_at       timestamp,
    deleted_at       timestamp,
    updated_at       timestamp,
    account_id       BIGINT UNSIGNED                not null,
    is_default       boolean default false not null,
    type             varchar(20)           not null,
    CONSTRAINT calculation_method_check
        CHECK (calculation_type IN ('FIXED', 'PERCENTAGE')),

    CONSTRAINT type_check
        CHECK (type IN ('TAX', 'FEE'))
);

ALTER TABLE taxes_and_fees
MODIFY COLUMN is_default BOOLEAN DEFAULT FALSE NOT NULL COMMENT 'Whether to apply to all new tickets automatically';

create index tax_and_fees_account_id_index
    on taxes_and_fees (account_id);

create table if not exists images
(
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_id   BIGINT UNSIGNED,
    entity_type varchar(120),
    type        varchar(40),
    filename    varchar(255),
    disk        varchar(20),
    path        text,
    size        integer,
    mime_type   varchar(50),
    created_at  timestamp default CURRENT_TIMESTAMP,
    updated_at  timestamp default CURRENT_TIMESTAMP,
    deleted_at  timestamp
);

create index idx_images_entity_id
    on images (entity_id);

create index idx_images_type
    on images (type);

create index idx_images_entity_type
    on images (entity_type);

create table if not exists organizers
(
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id  BIGINT UNSIGNED                                     not null,
    name        varchar(255)                                not null,
    email       varchar(255)                                not null,
    phone       varchar(20),
    website     varchar(255),
    description text,
    created_at  timestamp                                   not null,
    updated_at  timestamp                                   not null,
    deleted_at  timestamp,
    currency    varchar(3) not null default 'USD',
    timezone    varchar(255)                                not null,
    
    foreign key (account_id) references accounts(id)
);

create table if not exists events
(
    id                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title                     varchar(255)                                not null,
    account_id                BIGINT UNSIGNED                                     not null,
    user_id                   BIGINT UNSIGNED                                     not null,
    start_date                timestamp,
    end_date                  timestamp,
    description               text,
    status                    TEXT,
    location_details          JSON,
    currency                  varchar(3) not null default 'USD',
    timezone                  TEXT,
    attributes                JSON,
    created_at                timestamp                                   not null,
    updated_at                timestamp                                   not null,
    deleted_at                timestamp,
    location                  varchar(255),
    organizer_id              BIGINT UNSIGNED,
    short_id                  varchar(32)                                 not null,
    ticket_quantity_available integer,
    
    constraint fk_events_account_id
        foreign key (account_id) references accounts(id)
            on update cascade on delete cascade,
    constraint fk_events_user_id
        foreign key (user_id) references users(id)
            on delete cascade,
    constraint events_organizers_id_fk
        foreign key (organizer_id) references organizers(id)
);

create index events_account_id_index
    on events (account_id);

create index events_user_id_index
    on events (user_id);

create index events_organizer_id_index
    on events (organizer_id);

create table if not exists tickets
(
    id                           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title                        varchar(255)                                     not null,
    event_id                     BIGINT UNSIGNED                                          not null,
    sale_start_date              timestamp,
    sale_end_date                timestamp,
    max_per_order                integer,
    description                  text,
    min_per_order                integer,
    sales_volume                 DECIMAL(14, 2) not null default 0.00,
    sales_tax_volume             DECIMAL(14, 2) not null default 0.00,
    hide_before_sale_start_date  boolean        not null default false,
    hide_after_sale_end_date     boolean        not null default false,
    hide_when_sold_out           boolean        not null default false,
    show_quantity_remaining      boolean        not null default false,
    is_hidden_without_promo_code boolean        not null default false,
    `order`                      integer                                          not null,
    created_at                   timestamp                                        not null,
    updated_at                   timestamp,
    deleted_at                   timestamp,
    type                         varchar(20)    not null default 'PAID',
    is_hidden                    boolean        default false,
    
    constraint fk_tickets_event_id
        foreign key (event_id) references events(id)
            on delete cascade
);

create table if not exists promo_codes
(
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                  varchar(50)                 not null,
    discount              DECIMAL(14, 2) default 0.00 not null,
    applicable_ticket_ids JSON,
    expiry_date           timestamp,
    event_id              BIGINT UNSIGNED                      not null,
    discount_type         TEXT,
    attendee_usage_count  integer        default 0    not null,
    order_usage_count     integer        default 0    not null,
    max_allowed_usages    integer,
    created_at            timestamp                   not null,
    updated_at            timestamp,
    deleted_at            timestamp,
    
    constraint promo_codes_events_id_fk
        foreign key (event_id) references events(id)
);

create index promo_codes_code_index
    on promo_codes (code);

create index promo_codes_event_id_index
    on promo_codes (event_id);

-- TODO: INDEX ON JSON PATH
-- create index promo_codes_applicable_ticket_ids_index
--     on promo_codes (applicable_ticket_ids);

create table if not exists orders
(
    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    short_id               varchar(20)                  not null,
    event_id               BIGINT UNSIGNED                      not null,
    total_before_additions DECIMAL(14, 2) default 0.00  not null,
    total_refunded         DECIMAL(14, 2) default 0.00  not null,
    total_gross            DECIMAL(14, 2) default 0.00  not null,
    currency               varchar(3)                   not null,
    first_name             varchar(50),
    last_name              varchar(50),
    email                  varchar(255),
    status                 TEXT                     not null,
    payment_status         TEXT,
    refund_status          TEXT,
    reserved_until         timestamp(0),
    is_manually_created    boolean        default false not null,
    session_id             varchar(40),
    public_id              text                     not null, -- ARBITRARY LENGTH 300
    point_in_time_data     JSON,
    payment_gateway        TEXT,
    promo_code_id          BIGINT UNSIGNED,
    promo_code             TEXT,
    address                JSON,
    created_at             timestamp                    not null,
    updated_at             timestamp,
    deleted_at             timestamp,
    taxes_and_fees_rollup  JSON,
    total_tax              DECIMAL(14, 2) default 0.00  not null,
    total_fee              DECIMAL(14, 2) default 0.00  not null,
    
    constraint orders_pk
        unique (public_id(255)),
    constraint fk_orders_event_id
        foreign key (event_id) references events(id),
    constraint orders_promo_codes_id_fk
        foreign key (promo_code_id) references promo_codes(id)
);

create index orders_promo_code_id_index
    on orders (promo_code_id);

-- using fulltext instead of gin because it does not exist
create FULLTEXT index idx_orders_first_name_fulltext
    on orders (first_name);

create FULLTEXT index idx_orders_last_name_fulltext
    on orders (last_name);

create FULLTEXT index idx_orders_email_fulltext
    on orders (email);

create FULLTEXT index idx_orders_public_id_fulltext
    on orders (public_id);

create table if not exists questions
(
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id   BIGINT UNSIGNED                not null,
    title      text                  not null,
    required   boolean default false not null,
    type       TEXT,
    options    JSON,
    belongs_to TEXT               not null,
    created_at timestamp             not null,
    updated_at timestamp             not null,
    deleted_at timestamp,
    `order`    integer not null default 1,
    is_hidden  boolean default false not null,
    
    constraint questions_event_id_fk
        foreign key (event_id) references events(id)
);

create table if not exists stripe_payments
(
    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id             BIGINT UNSIGNED  not null,
    payment_intent_id    TEXT not null,
    charge_id            TEXT,
    payment_method_id    TEXT,
    amount_received      BIGINT UNSIGNED,
    created_at           timestamp,
    updated_at           timestamp,
    deleted_at           timestamp,
    last_error           json,
    connected_account_id varchar(50),
    
    constraint stripe_payments_orders_id_fk
        foreign key (order_id) references orders(id)
);

create table if not exists messages
(
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id        BIGINT UNSIGNED       not null,
    subject         varchar(255) not null,
    message         text         not null,
    type            varchar(40)  not null,
    recipient_ids   JSON,
    sent_at         timestamp,
    sent_by_user_id BIGINT UNSIGNED       not null,
    attendee_ids    JSON,
    ticket_ids      JSON,
    order_id        BIGINT UNSIGNED,
    status          varchar(20)  not null,
    send_data       JSON,
    created_at      timestamp    not null,
    updated_at      timestamp,
    deleted_at      timestamp,
    
    constraint messages_events_id_fk
        foreign key (event_id) references events(id),
    constraint messages_users_id_fk
        foreign key (sent_by_user_id) references users(id)
);

create table if not exists affiliates
(
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            text           not null,
    event_id        BIGINT UNSIGNED,
    sales_volume    DECIMAL(14, 2),
    unique_visitors integer default 0 not null,
    created_at      timestamp         not null,
    updated_at      timestamp,
    deleted_at      timestamp,
    
    constraint affiliates_events_id_fk
        foreign key (event_id) references events(id)
);

create unique index affiliates_code_uindex
    on affiliates (code(255));

create index affiliates_event_id_index
    on affiliates (event_id);

create table if not exists event_statistics
(
    id                           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id                     BIGINT UNSIGNED                      not null,
    unique_views                 BIGINT UNSIGNED         default 0    not null,
    total_views                  BIGINT UNSIGNED         default 0    not null,
    sales_total_gross            DECIMAL(14, 2) default 0.00 not null,
    total_tax                    DECIMAL(14, 2) default 0.00 not null,
    sales_total_before_additions DECIMAL(14, 2) default 0.00 not null,
    created_at                   timestamp                   not null,
    deleted_at                   timestamp,
    updated_at                   timestamp,
    total_fee                    DECIMAL(14, 2) default 0.00 not null,
    tickets_sold                 integer        default 0    not null,
    version                      integer        default 0    not null,
    orders_created               integer        default 0    not null,
    total_refunded               DECIMAL(14, 2) default 0    not null,
    
    constraint event_statistics_events_id_fk
        foreign key (event_id) references events(id)
);

create index event_statistics_event_id_index
    on event_statistics (event_id);

create table if not exists event_daily_statistics
(
    id                           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_total_gross            DECIMAL(14, 2) default 0.00 not null,
    total_tax                    DECIMAL(14, 2) default 0.00 not null,
    sales_total_before_additions DECIMAL(14, 2) default 0.00 not null,
    tickets_sold                 integer        default 0    not null,
    orders_created               integer        default 0    not null,
    date                         date                        not null,
    created_at                   timestamp                   not null,
    deleted_at                   timestamp,
    updated_at                   timestamp,
    total_fee                    DECIMAL(14, 2) default 0    not null,
    event_id                     BIGINT UNSIGNED                      not null,
    version                      integer        default 0    not null,
    total_refunded               DECIMAL(14, 2) default 0    not null,
    total_views                  BIGINT UNSIGNED         default 0    not null,
    
    constraint event_daily_statistics_events_id_fk
        foreign key (event_id) references events(id)
);

create index event_daily_statistics_event_id_index
    on event_daily_statistics (event_id);

create table if not exists ticket_taxes_and_fees
(
    id             integer AUTO_INCREMENT PRIMARY KEY,
    ticket_id      BIGINT UNSIGNED not null,
    tax_and_fee_id BIGINT UNSIGNED not null,
    constraint ticket_tax_and_fees_tickets_id_fk
        foreign key (ticket_id) references tickets(id)
            on delete cascade,
    constraint ticket_tax_and_fees_tax_and_fees_id_fk
        foreign key (tax_and_fee_id) references taxes_and_fees(id)
            on delete cascade
);

create index ticket_tax_and_fees_tax_and_fee_id_index
    on ticket_taxes_and_fees (tax_and_fee_id);

create index ticket_tax_and_fees_ticket_id_index
    on ticket_taxes_and_fees (ticket_id);

create table if not exists ticket_prices
(
    id                         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id                  BIGINT UNSIGNED            not null,
    price                      DECIMAL(14, 2)    not null,
    label                      varchar(255),
    sale_start_date            timestamp,
    sale_end_date              timestamp,
    created_at                 timestamp         not null,
    updated_at                 timestamp,
    deleted_at                 timestamp,
    initial_quantity_available integer,
    quantity_sold              integer default 0 not null,
    is_hidden                  boolean default false,
    `order`                    integer default 1 not null,
    constraint fk_ticket_prices_ticket_id
        foreign key (ticket_id) references tickets(id)
            on delete cascade,
    check (price >= 0)
);

create index idx_ticket_prices_ticket_id
    on ticket_prices (ticket_id);

create index idx_ticket_prices_dates
    on ticket_prices (sale_start_date, sale_end_date);

create table if not exists order_items
(
    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    total_before_additions DECIMAL(14, 2)              not null,
    quantity               integer                     not null,
    order_id               BIGINT UNSIGNED                     not null,
    ticket_id              BIGINT UNSIGNED                     not null,
    item_name              TEXT,
    price                  DECIMAL(14, 2)              not null,
    price_before_discount  DECIMAL(14, 2),
    deleted_at             timestamp,
    total_tax              DECIMAL(14, 2) default 0.00 not null,
    total_gross            DECIMAL(14, 2),
    total_service_fee      DECIMAL(14, 2) default 0.00,
    taxes_and_fees_rollup  JSON,
    ticket_price_id        BIGINT UNSIGNED                     not null,
    
    constraint fk_order_items_order_id
        foreign key (order_id) references orders(id)
            on delete cascade,
    constraint fk_order_items_ticket_id
        foreign key (ticket_id) references tickets(id),
    constraint order_items_ticket_prices_id_fk
        foreign key (ticket_price_id) references ticket_prices(id)
);

create index order_items_order_id_index
    on order_items (order_id);

create index order_items_ticket_id_index
    on order_items (ticket_id);

create index order_items_ticket_price_id_index
    on order_items (ticket_price_id);

create table if not exists attendees
(
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    short_id        TEXT                                    not null,
    first_name      varchar(255) not null default '',
    last_name       varchar(255) not null default '',
    email           varchar(255)                               not null,
    order_id        BIGINT UNSIGNED                                    not null,
    ticket_id       BIGINT UNSIGNED                                    not null,
    event_id        BIGINT UNSIGNED                                    not null,
    public_id       TEXT                                    not null,
    status          varchar(20)                                not null,
    checked_in_by   BIGINT UNSIGNED,
    checked_in_at   timestamp,
    created_at      timestamp                                  not null,
    updated_at      timestamp                                  not null,
    deleted_at      timestamp,
    checked_out_by  BIGINT UNSIGNED,
    ticket_price_id BIGINT UNSIGNED                                     not null,
    
    constraint fk_attendees_order_id
        foreign key (order_id) references orders(id)
            on delete cascade,
    constraint fk_attendees_ticket_id
        foreign key (ticket_id) references tickets(id)
            on delete cascade,
    constraint attendees_events_id_fk
        foreign key (event_id) references events(id)
            on delete cascade,
    constraint fk_attendees_checked_in_by_id
        foreign key (checked_in_by) references users(id)
            on delete cascade,
    constraint attendees_users_id_fk
        foreign key (checked_out_by) references users(id),
    constraint attendees_ticket_prices_id_fk
        foreign key (ticket_price_id) references ticket_prices(id)
);

create FULLTEXT index idx_attendees_first_name_fulltext
    on attendees (first_name);

create FULLTEXT index idx_attendees_last_name_fulltext
    on attendees (last_name);

create FULLTEXT index idx_attendees_email_fulltext
    on attendees (email);

create FULLTEXT index idx_attendees_public_id_fulltext
    on attendees (public_id);

ALTER TABLE attendees
    ADD COLUMN public_id_lower VARCHAR(255) GENERATED ALWAYS AS (LOWER(public_id)) STORED;

CREATE INDEX idx_attendees_public_id_lower
    ON attendees (public_id_lower);
-- TODO: code has to use column public_id_lower instead of public_id when using strtolower

create table if not exists question_answers
(
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED   not null,
    order_id    BIGINT UNSIGNED   not null,
    attendee_id BIGINT UNSIGNED,
    ticket_id   BIGINT UNSIGNED,
    created_at  timestamp not null,
    updated_at  timestamp not null,
    deleted_at  timestamp,
    answer      JSON,
    
    constraint fk_question_answers_question_id
        foreign key (question_id) references questions(id),
    constraint fk_orders_order_id
        foreign key (order_id) references orders(id),
    constraint fk_attendeed_attendee_id
        foreign key (attendee_id) references attendees(id),
    constraint fk_tickets_ticket_id
        foreign key (ticket_id) references tickets(id)
);

create index question_answers_attendee_id_index
    on question_answers (attendee_id);

create index question_answers_order_id_index
    on question_answers (order_id);

create index question_answers_question_id_index
    on question_answers (question_id);

create table if not exists ticket_questions
(
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   BIGINT UNSIGNED not null,
    question_id BIGINT UNSIGNED not null,
    deleted_at  timestamp,
    constraint fk_ticket_questions_ticket_id
        foreign key (ticket_id) references tickets(id)
            on delete cascade,
    constraint fk_ticket_questions_question_id
        foreign key (question_id) references questions(id)
            on delete cascade
);
ALTER TABLE ticket_questions
    ADD COLUMN is_active TINYINT(1) AS (IF(deleted_at IS NULL, 1, 0)) STORED;
-- Create a unique index on active rows only (emulated via the generated column)
CREATE UNIQUE INDEX idx_ticket_questions_active
    ON ticket_questions (ticket_id, question_id, is_active);

create table if not exists event_settings
(
    id                              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pre_checkout_message            text,
    post_checkout_message           text,
    ticket_page_message             text,
    continue_button_text            varchar(100),
    email_footer_message            text,
    support_email                   varchar(255),
    event_id                        BIGINT UNSIGNED                                              not null,
    created_at                      timestamp                                           not null,
    updated_at                      timestamp                                           not null,
    deleted_at                      timestamp,
    require_attendee_details        boolean      default true                           not null,
    order_timeout_in_minutes        integer      default 15                             not null,
    website_url                     varchar(400),
    maps_url                        varchar(400),
    homepage_background_color       varchar(20),
    homepage_primary_text_color     varchar(20),
    homepage_primary_color          varchar(20),
    homepage_secondary_text_color   varchar(20),
    homepage_secondary_color        varchar(20),
    location_details                JSON,
    online_event_connection_details text,
    is_online_event                 boolean      default false                          not null,
    allow_search_engine_indexing    boolean      default true                           not null,
    seo_title                       varchar(255),
    seo_description                 varchar(255),
    social_media_handles            JSON,
    show_social_media_handles       boolean,
    seo_keywords                    varchar(255),
    notify_organizer_of_new_orders  boolean      default true                           not null,
    price_display_mode              varchar(255) not null default 'INCLUSIVE',
    hide_getting_started_page       boolean      default false                          not null,
    show_share_buttons              boolean      default true                           not null,
    constraint event_settings_events_id_fk
        foreign key (event_id) references events(id)
            on delete cascade,
    CONSTRAINT event_settings_price_display_mode_check
        CHECK (price_display_mode IN ('INCLUSIVE', 'EXCLUSIVE'))
);

create index event_settings_event_id_index
    on event_settings (event_id);

create table if not exists account_users
(
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id         BIGINT UNSIGNED                                           not null,
    user_id            BIGINT UNSIGNED                                           not null,
    role               varchar(100),
    created_at         timestamp   default now(),
    deleted_at         timestamp,
    updated_at         timestamp,
    is_account_owner   boolean     default false                        not null,
    invited_by_user_id BIGINT UNSIGNED,
    last_login_at      timestamp,
    status             varchar(40) default 'INVITED' not null,
    
    unique (account_id, user_id, role),
    constraint fk_account_users_accounts
        foreign key (account_id) references accounts(id)
            on delete cascade,
    constraint fk_account_users_users
        foreign key (user_id) references users(id)
            on delete cascade,
    constraint account_users_users_id_fk
        foreign key (invited_by_user_id) references users(id)
);

create index idx_account_users_account_id
    on account_users (account_id);

create index idx_account_users_user_id
    on account_users (user_id);

create index idx_account_users_role
    on account_users (role);

create view question_and_answer_views
        (question_id, event_id, belongs_to, question_type, first_name, last_name, attendee_id, order_id, title,
         answer, question_answer_id)
as
SELECT q.id   AS question_id,
       q.event_id,
       q.belongs_to,
       q.type AS question_type,
       a.first_name,
       a.last_name,
       a.id   AS attendee_id,
       qa.order_id,
       q.title,
       qa.answer,
       qa.id as question_answer_id
FROM question_answers qa
         LEFT JOIN attendees a ON a.id = qa.attendee_id
         JOIN orders o ON qa.order_id = o.id
         JOIN questions q ON q.id = qa.question_id;

