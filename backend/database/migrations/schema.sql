-- DO NOT MODIFY THIS FILE. Create a new migration file instead.

create table if not exists migrations
(
    id        serial,
    migration varchar(255) not null,
    batch     integer      not null,
    primary key (id)
);

create table if not exists personal_access_tokens
(
    id             bigserial,
    tokenable_type varchar(255) not null,
    tokenable_id   bigint       not null,
    name           varchar(255) not null,
    token          varchar(64)  not null,
    abilities      text,
    last_used_at   timestamp(0),
    expires_at     timestamp(0),
    created_at     timestamp(0),
    updated_at     timestamp(0),
    primary key (id),
    constraint personal_access_tokens_token_unique
        unique (token)
);

create index if not exists personal_access_tokens_tokenable_type_tokenable_id_index
    on personal_access_tokens (tokenable_type, tokenable_id);

create table if not exists password_reset_tokens
(
    email      varchar(255) not null,
    token      varchar(255) not null,
    created_at timestamp,
    deleted_at timestamp,
    updated_at timestamp,
    id         bigint generated always as identity
);

create index if not exists password_reset_tokens_email_index
    on password_reset_tokens (email);

create index if not exists password_reset_tokens_token_index
    on password_reset_tokens (token);

create table if not exists failed_jobs
(
    id         bigint generated always as identity,
    uuid       varchar(255)                           not null,
    connection text                                   not null,
    queue      text                                   not null,
    payload    text                                   not null,
    exception  text                                   not null,
    failed_at  timestamp(0) default CURRENT_TIMESTAMP not null,
    primary key (id),
    constraint failed_jobs_uuid_unique
        unique (uuid)
);

create table if not exists accounts
(
    id                            bigint generated always as identity,
    currency_code                 varchar(3) default 'USD'::character varying not null,
    timezone                      varchar(255),
    created_at                    timestamp,
    updated_at                    timestamp,
    deleted_at                    timestamp,
    name                          varchar                                     not null,
    email                         varchar                                     not null,
    stripe_account_id             varchar(50),
    short_id                      varchar(20)                                 not null,
    stripe_connect_setup_complete boolean    default false,
    account_verified_at           timestamp,
    primary key (id)
);

create table if not exists password_resets
(
    email      varchar(200),
    token      varchar(200),
    created_at timestamp,
    updated_at timestamp,
    deleted_at timestamp,
    id         bigint generated always as identity,
    ip_address varchar not null,
    user_agent varchar
);

create table if not exists timezones
(
    id         bigint generated always as identity,
    name       varchar(64) not null,
    deleted_at timestamp,
    primary key (id)
);

create table if not exists roles
(
    id          bigint generated always as identity,
    name        varchar,
    permissions jsonb not null,
    account_id  bigint,
    constraint roles_pk
        primary key (id),
    constraint roles_accounts_id_fk
        foreign key (account_id) references accounts
);

create table if not exists users
(
    id                bigint generated always as identity,
    email             varchar(255) not null,
    email_verified_at timestamp(0),
    password          varchar(255) not null,
    remember_token    varchar(100),
    created_at        timestamp(0),
    updated_at        timestamp(0),
    deleted_at        timestamp,
    first_name        varchar      not null,
    last_name         varchar,
    pending_email     varchar,
    timezone          varchar      not null,
    primary key (id)
);

create table if not exists event_logs
(
    id          bigint generated always as identity,
    user_id     bigint       not null,
    type        varchar(255) not null,
    entity_id   bigint       not null,
    entity_type bigint       not null,
    ip_address  varchar,
    user_agent  varchar,
    data        jsonb,
    created_at  timestamp    not null,
    updated_at  timestamp,
    deleted_at  timestamp,
    primary key (id),
    constraint event_logs_users_id_fk
        foreign key (user_id) references users
);

create table if not exists taxes_and_fees
(
    id               bigint generated always as identity,
    name             varchar(255)          not null,
    calculation_type varchar(20)           not null,
    rate             numeric(10, 3)        not null,
    is_active        boolean default true,
    description      text,
    created_at       timestamp,
    deleted_at       timestamp,
    updated_at       timestamp,
    account_id       bigint                not null,
    is_default       boolean default false not null,
    type             varchar(20)           not null,
    constraint tax_and_fee_types_pkey
        primary key (id),
    constraint calculation_method_check
        check ((calculation_type)::text = ANY
               (ARRAY [('FIXED'::character varying)::text, ('PERCENTAGE'::character varying)::text])),
    constraint type_check
        check ((type)::text = ANY (ARRAY [('TAX'::character varying)::text, ('FEE'::character varying)::text]))
);

comment on column taxes_and_fees.is_default is 'Whether to apply to all new tickets automatically';

create index if not exists tax_and_fees_account_id_index
    on taxes_and_fees (account_id);

create table if not exists images
(
    id          bigserial,
    entity_id   bigint,
    entity_type varchar(120),
    type        varchar(40),
    filename    varchar(255),
    disk        varchar(20),
    path        text,
    size        integer,
    mime_type   varchar(50),
    created_at  timestamp default CURRENT_TIMESTAMP,
    updated_at  timestamp default CURRENT_TIMESTAMP,
    deleted_at  timestamp,
    primary key (id)
);

create index if not exists idx_images_entity_id
    on images (entity_id);

create index if not exists idx_images_type
    on images (type);

create index if not exists idx_images_entity_type
    on images (entity_type);

create table if not exists organizers
(
    id          bigint generated always as identity,
    account_id  integer                                     not null,
    name        varchar(255)                                not null,
    email       varchar(255)                                not null,
    phone       varchar(20),
    website     varchar(255),
    description text,
    created_at  timestamp                                   not null,
    updated_at  timestamp                                   not null,
    deleted_at  timestamp,
    currency    varchar(3) default 'USD'::character varying not null,
    timezone    varchar(255)                                not null,
    primary key (id),
    foreign key (account_id) references accounts
);

create table if not exists events
(
    id                        bigint generated always as identity,
    title                     varchar(255)                                not null,
    account_id                integer                                     not null,
    user_id                   integer                                     not null,
    start_date                timestamp,
    end_date                  timestamp,
    description               text,
    status                    varchar,
    location_details          jsonb,
    currency                  varchar(3) default 'USD'::character varying not null,
    timezone                  varchar,
    attributes                jsonb,
    created_at                timestamp                                   not null,
    updated_at                timestamp                                   not null,
    deleted_at                timestamp,
    location                  varchar(255),
    organizer_id              bigint,
    short_id                  varchar(32)                                 not null,
    ticket_quantity_available integer,
    primary key (id),
    constraint fk_events_account_id
        foreign key (account_id) references accounts
            on update cascade on delete cascade,
    constraint fk_events_user_id
        foreign key (user_id) references users
            on delete cascade,
    constraint events_organizers_id_fk
        foreign key (organizer_id) references organizers
);

create index if not exists events_account_id_index
    on events (account_id);

create index if not exists events_user_id_index
    on events (user_id);

create index if not exists events_organizer_id_index
    on events (organizer_id);

create table if not exists tickets
(
    id                           bigint generated always as identity,
    title                        varchar(255)                                     not null,
    event_id                     integer                                          not null,
    sale_start_date              timestamp,
    sale_end_date                timestamp,
    max_per_order                integer,
    description                  text,
    min_per_order                integer,
    sales_volume                 numeric(14, 2) default 0.00                      not null,
    sales_tax_volume             numeric(14, 2) default 0.00                      not null,
    hide_before_sale_start_date  boolean        default false                     not null,
    hide_after_sale_end_date     boolean        default false                     not null,
    hide_when_sold_out           boolean        default false                     not null,
    show_quantity_remaining      boolean        default false                     not null,
    is_hidden_without_promo_code boolean        default false                     not null,
    "order"                      integer                                          not null,
    created_at                   timestamp                                        not null,
    updated_at                   timestamp,
    deleted_at                   timestamp,
    type                         varchar(20)    default 'PAID'::character varying not null,
    is_hidden                    boolean        default false,
    primary key (id),
    constraint fk_tickets_event_id
        foreign key (event_id) references events
            on delete cascade
);

create table if not exists promo_codes
(
    id                    bigint generated always as identity,
    code                  varchar(50)                 not null,
    discount              numeric(14, 2) default 0.00 not null,
    applicable_ticket_ids jsonb,
    expiry_date           timestamp with time zone,
    event_id              bigint                      not null,
    discount_type         varchar,
    attendee_usage_count  integer        default 0    not null,
    order_usage_count     integer        default 0    not null,
    max_allowed_usages    integer,
    created_at            timestamp                   not null,
    updated_at            timestamp,
    deleted_at            timestamp,
    primary key (id),
    constraint promo_codes_events_id_fk
        foreign key (event_id) references events
);

create index if not exists promo_codes_code_index
    on promo_codes (code);

create index if not exists promo_codes_event_id_index
    on promo_codes (event_id);

create index if not exists promo_codes_applicable_ticket_ids_index
    on promo_codes (applicable_ticket_ids);

create table if not exists orders
(
    id                     bigint generated always as identity,
    short_id               varchar(20)                  not null,
    event_id               integer                      not null,
    total_before_additions numeric(14, 2) default 0.00  not null,
    total_refunded         numeric(14, 2) default 0.00  not null,
    total_gross            numeric(14, 2) default 0.00  not null,
    currency               varchar(3)                   not null,
    first_name             varchar(50),
    last_name              varchar(50),
    email                  varchar(255),
    status                 varchar                      not null,
    payment_status         varchar,
    refund_status          varchar,
    reserved_until         timestamp(0),
    is_manually_created    boolean        default false not null,
    session_id             varchar(40),
    public_id              varchar                      not null,
    point_in_time_data     jsonb,
    payment_gateway        varchar,
    promo_code_id          integer,
    promo_code             varchar,
    address                jsonb,
    created_at             timestamp                    not null,
    updated_at             timestamp,
    deleted_at             timestamp,
    taxes_and_fees_rollup  jsonb,
    total_tax              numeric(14, 2) default 0.00  not null,
    total_fee              numeric(14, 2) default 0.00  not null,
    primary key (id),
    constraint orders_pk
        unique (public_id),
    constraint fk_orders_event_id
        foreign key (event_id) references events,
    constraint orders_promo_codes_id_fk
        foreign key (promo_code_id) references promo_codes
);

create index if not exists orders_promo_code_id_index
    on orders (promo_code_id);

create index if not exists idx_orders_first_name_trgm
    on orders using gin (first_name gin_trgm_ops);

create index if not exists idx_orders_last_name_trgm
    on orders using gin (last_name gin_trgm_ops);

create index if not exists idx_orders_email_trgm
    on orders using gin (email gin_trgm_ops);

create index if not exists idx_orders_public_id_trgm
    on orders using gin (public_id gin_trgm_ops);

create table if not exists questions
(
    id         bigint generated always as identity,
    event_id   bigint                not null,
    title      text                  not null,
    required   boolean default false not null,
    type       varchar,
    options    jsonb,
    belongs_to varchar               not null,
    created_at timestamp             not null,
    updated_at timestamp             not null,
    deleted_at timestamp,
    "order"    integer default 1     not null,
    is_hidden  boolean default false not null,
    primary key (id),
    constraint questions_event_id_fk
        foreign key (event_id) references events
);

create table if not exists stripe_payments
(
    id                   bigint generated always as identity,
    order_id             bigint  not null,
    payment_intent_id    varchar not null,
    charge_id            varchar,
    payment_method_id    varchar,
    amount_received      bigint,
    created_at           timestamp,
    updated_at           timestamp,
    deleted_at           timestamp,
    last_error           json,
    connected_account_id varchar(50),
    primary key (id),
    constraint stripe_payments_orders_id_fk
        foreign key (order_id) references orders
);

create table if not exists messages
(
    id              bigint generated always as identity,
    event_id        bigint       not null,
    subject         varchar(255) not null,
    message         text         not null,
    type            varchar(40)  not null,
    recipient_ids   jsonb,
    sent_at         timestamp,
    sent_by_user_id bigint       not null,
    attendee_ids    jsonb,
    ticket_ids      jsonb,
    order_id        bigint,
    status          varchar(20)  not null,
    send_data       jsonb,
    created_at      timestamp    not null,
    updated_at      timestamp,
    deleted_at      timestamp,
    primary key (id),
    constraint messages_events_id_fk
        foreign key (event_id) references events,
    constraint messages_users_id_fk
        foreign key (sent_by_user_id) references users
);

create table if not exists affiliates
(
    id              bigint generated always as identity,
    code            varchar           not null,
    event_id        bigint,
    sales_volume    numeric(14, 2),
    unique_visitors integer default 0 not null,
    created_at      timestamp         not null,
    updated_at      timestamp,
    deleted_at      timestamp,
    primary key (id),
    constraint affiliates_events_id_fk
        foreign key (event_id) references events
);

create unique index if not exists affiliates_code_uindex
    on affiliates (code);

create index if not exists affiliates_event_id_index
    on affiliates (event_id);

create table if not exists event_statistics
(
    id                           bigint generated always as identity,
    event_id                     bigint                      not null,
    unique_views                 bigint         default 0    not null,
    total_views                  bigint         default 0    not null,
    sales_total_gross            numeric(14, 2) default 0.00 not null,
    total_tax                    numeric(14, 2) default 0.00 not null,
    sales_total_before_additions numeric(14, 2) default 0.00 not null,
    created_at                   timestamp                   not null,
    deleted_at                   timestamp,
    updated_at                   timestamp,
    total_fee                    numeric(14, 2) default 0.00 not null,
    tickets_sold                 integer        default 0    not null,
    version                      integer        default 0    not null,
    orders_created               integer        default 0    not null,
    total_refunded               numeric(14, 2) default 0    not null,
    primary key (id),
    constraint event_statistics_events_id_fk
        foreign key (event_id) references events
);

create index if not exists event_statistics_event_id_index
    on event_statistics (event_id);

create table if not exists event_daily_statistics
(
    id                           bigint generated always as identity,
    sales_total_gross            numeric(14, 2) default 0.00 not null,
    total_tax                    numeric(14, 2) default 0.00 not null,
    sales_total_before_additions numeric(14, 2) default 0.00 not null,
    tickets_sold                 integer        default 0    not null,
    orders_created               integer        default 0    not null,
    date                         date                        not null,
    created_at                   timestamp                   not null,
    deleted_at                   timestamp,
    updated_at                   timestamp,
    total_fee                    numeric(14, 2) default 0    not null,
    event_id                     bigint                      not null,
    version                      integer        default 0    not null,
    total_refunded               numeric(14, 2) default 0    not null,
    total_views                  bigint         default 0    not null,
    primary key (id),
    constraint event_daily_statistics_events_id_fk
        foreign key (event_id) references events
);

create index if not exists event_daily_statistics_event_id_index
    on event_daily_statistics (event_id);

create table if not exists ticket_taxes_and_fees
(
    id             integer generated always as identity,
    ticket_id      bigint not null,
    tax_and_fee_id bigint not null,
    constraint ticket_tax_and_fees_pk
        primary key (id),
    constraint ticket_tax_and_fees_tickets_id_fk
        foreign key (ticket_id) references tickets
            on delete cascade,
    constraint ticket_tax_and_fees_tax_and_fees_id_fk
        foreign key (tax_and_fee_id) references taxes_and_fees
            on delete cascade
);

create index if not exists ticket_tax_and_fees_tax_and_fee_id_index
    on ticket_taxes_and_fees (tax_and_fee_id);

create index if not exists ticket_tax_and_fees_ticket_id_index
    on ticket_taxes_and_fees (ticket_id);

create table if not exists ticket_prices
(
    id                         bigint generated always as identity,
    ticket_id                  bigint            not null,
    price                      numeric(14, 2)    not null,
    label                      varchar(255),
    sale_start_date            timestamp,
    sale_end_date              timestamp,
    created_at                 timestamp         not null,
    updated_at                 timestamp,
    deleted_at                 timestamp,
    initial_quantity_available integer,
    quantity_sold              integer default 0 not null,
    is_hidden                  boolean default false,
    "order"                    integer default 1 not null,
    constraint pk_ticket_prices
        primary key (id),
    constraint fk_ticket_prices_ticket_id
        foreign key (ticket_id) references tickets
            on delete cascade,
    constraint valid_price_range
        check (price >= (0)::numeric)
);

create index if not exists idx_ticket_prices_ticket_id
    on ticket_prices (ticket_id);

create index if not exists idx_ticket_prices_dates
    on ticket_prices (sale_start_date, sale_end_date);

create table if not exists order_items
(
    id                     bigint generated always as identity,
    total_before_additions numeric(14, 2)              not null,
    quantity               integer                     not null,
    order_id               integer                     not null,
    ticket_id              integer                     not null,
    item_name              varchar,
    price                  numeric(14, 2)              not null,
    price_before_discount  numeric(14, 2),
    deleted_at             timestamp,
    total_tax              numeric(14, 2) default 0.00 not null,
    total_gross            numeric(14, 2),
    total_service_fee      numeric(14, 2) default 0.00,
    taxes_and_fees_rollup  jsonb,
    ticket_price_id        integer                     not null,
    primary key (id),
    constraint fk_order_items_order_id
        foreign key (order_id) references orders
            on delete cascade,
    constraint fk_order_items_ticket_id
        foreign key (ticket_id) references tickets,
    constraint order_items_ticket_prices_id_fk
        foreign key (ticket_price_id) references ticket_prices
);

create index if not exists order_items_order_id_index
    on order_items (order_id);

create index if not exists order_items_ticket_id_index
    on order_items (ticket_id);

create index if not exists order_items_ticket_price_id_index
    on order_items (ticket_price_id);

create table if not exists attendees
(
    id              bigint generated always as identity,
    short_id        varchar                                    not null,
    first_name      varchar(255) default ''::character varying not null,
    last_name       varchar(255) default ''::character varying not null,
    email           varchar(255)                               not null,
    order_id        integer                                    not null,
    ticket_id       integer                                    not null,
    event_id        integer                                    not null,
    public_id       varchar                                    not null,
    status          varchar(20)                                not null,
    checked_in_by   bigint,
    checked_in_at   timestamp,
    created_at      timestamp                                  not null,
    updated_at      timestamp                                  not null,
    deleted_at      timestamp,
    checked_out_by  bigint,
    ticket_price_id bigint                                     not null,
    primary key (id),
    constraint fk_attendees_order_id
        foreign key (order_id) references orders
            on delete cascade,
    constraint fk_attendees_ticket_id
        foreign key (ticket_id) references tickets
            on delete cascade,
    constraint attendees_events_id_fk
        foreign key (event_id) references events
            on delete cascade,
    constraint fk_attendees_checked_in_by_id
        foreign key (checked_in_by) references users
            on delete cascade,
    constraint attendees_users_id_fk
        foreign key (checked_out_by) references users,
    constraint attendees_ticket_prices_id_fk
        foreign key (ticket_price_id) references ticket_prices
);

create index if not exists idx_attendees_first_name_trgm
    on attendees using gin (first_name gin_trgm_ops);

create index if not exists idx_attendees_last_name_trgm
    on attendees using gin (last_name gin_trgm_ops);

create index if not exists idx_attendees_email_trgm
    on attendees using gin (email gin_trgm_ops);

create index if not exists idx_attendees_public_id_trgm
    on attendees using gin (public_id gin_trgm_ops);

create index if not exists idx_attendees_public_id_lower
    on attendees (lower(public_id::text));

create table if not exists question_answers
(
    id          bigint generated always as identity,
    question_id integer   not null,
    order_id    integer   not null,
    attendee_id integer,
    ticket_id   integer,
    created_at  timestamp not null,
    updated_at  timestamp not null,
    deleted_at  timestamp,
    answer      jsonb,
    primary key (id),
    constraint fk_question_answers_question_id
        foreign key (question_id) references questions,
    constraint fk_orders_order_id
        foreign key (order_id) references orders,
    constraint fk_attendeed_attendee_id
        foreign key (attendee_id) references attendees,
    constraint fk_tickets_ticket_id
        foreign key (ticket_id) references tickets
);

create index if not exists question_answers_attendee_id_index
    on question_answers (attendee_id);

create index if not exists question_answers_order_id_index
    on question_answers (order_id);

create index if not exists question_answers_question_id_index
    on question_answers (question_id);

create table if not exists ticket_questions
(
    id          serial,
    ticket_id   integer not null,
    question_id integer not null,
    deleted_at  timestamp,
    primary key (id),
    constraint fk_ticket_questions_ticket_id
        foreign key (ticket_id) references tickets
            on delete cascade,
    constraint fk_ticket_questions_question_id
        foreign key (question_id) references questions
            on delete cascade
);

create unique index if not exists idx_ticket_questions_active
    on ticket_questions (ticket_id, question_id)
    where (deleted_at IS NULL);

create table if not exists event_settings
(
    id                              bigint generated always as identity,
    pre_checkout_message            text,
    post_checkout_message           text,
    ticket_page_message             text,
    continue_button_text            varchar(100),
    email_footer_message            text,
    support_email                   varchar(255),
    event_id                        bigint                                              not null,
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
    location_details                jsonb,
    online_event_connection_details text,
    is_online_event                 boolean      default false                          not null,
    allow_search_engine_indexing    boolean      default true                           not null,
    seo_title                       varchar(255),
    seo_description                 varchar(255),
    social_media_handles            jsonb,
    show_social_media_handles       boolean,
    seo_keywords                    varchar(255),
    notify_organizer_of_new_orders  boolean      default true                           not null,
    price_display_mode              varchar(255) default 'INCLUSIVE'::character varying not null,
    hide_getting_started_page       boolean      default false                          not null,
    show_share_buttons              boolean      default true                           not null,
    constraint event_settings_pk
        primary key (id),
    constraint event_settings_events_id_fk
        foreign key (event_id) references events
            on delete cascade,
    constraint event_settings_price_display_mode_check
        check ((price_display_mode)::text = ANY
               (ARRAY [('INCLUSIVE'::character varying)::text, ('EXCLUSIVE'::character varying)::text]))
);

create index if not exists event_settings_event_id_index
    on event_settings (event_id);

create table if not exists account_users
(
    id                 bigint generated always as identity,
    account_id         bigint                                           not null,
    user_id            bigint                                           not null,
    role               varchar(100),
    created_at         timestamp   default now(),
    deleted_at         timestamp,
    updated_at         timestamp,
    is_account_owner   boolean     default false                        not null,
    invited_by_user_id bigint,
    last_login_at      timestamp,
    status             varchar(40) default 'INVITED'::character varying not null,
    primary key (id),
    unique (account_id, user_id, role),
    constraint fk_account_users_accounts
        foreign key (account_id) references accounts
            on delete cascade,
    constraint fk_account_users_users
        foreign key (user_id) references users
            on delete cascade,
    constraint account_users_users_id_fk
        foreign key (invited_by_user_id) references users
);

create index if not exists idx_account_users_account_id
    on account_users (account_id);

create index if not exists idx_account_users_user_id
    on account_users (user_id);

create index if not exists idx_account_users_role
    on account_users (role);

create view question_and_answer_views
        (question_id, event_id, belongs_to, question_type, first_name, last_name, attendee_id, order_id, title,
         answer)
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

