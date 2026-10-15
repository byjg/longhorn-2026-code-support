-- The gateway side: one row per payment the shop registered. `status` is the last event
-- the gateway reported (pending, requires_review, succeeded, declined, expired); the
-- gateway knows nothing about the state machine of the shop.
create table transactions
(
    id          integer primary key autoincrement,
    payment_id  integer        not null,
    amount      decimal(10, 2) not null,
    status      varchar(20)    not null,
    webhook_url varchar(250)   not null,
    created_at  datetime default current_timestamp,
    updated_at  datetime default current_timestamp
);

-- Every webhook the gateway sent, and the answer from the shop: the history the page shows.
create table webhook_call
(
    id              integer primary key autoincrement,
    transaction_id  integer     not null references transactions (id),
    event           varchar(20) not null,
    request_body    text        not null,
    response_status integer     not null,
    response_body   text,
    created_at      datetime default current_timestamp,
    updated_at      datetime default current_timestamp
);

-- SQLite has no ON UPDATE CURRENT_TIMESTAMP; this trigger is its equivalent.
-- The body stays on one line: byjg/migration splits a SQLite file on ";" + newline.
create trigger transaction_updated_at
    after update on transactions
    for each row
    when new.updated_at = old.updated_at
begin update transactions set updated_at = current_timestamp where id = new.id; end;
