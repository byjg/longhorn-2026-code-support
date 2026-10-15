-- The merchant's view of a payment. `status` is a case of Shop\Payment\PaymentState.
create table payment
(
    id              integer primary key autoincrement,
    amount          decimal(10, 2) not null,
    status          varchar(20)    not null,
    transition_name varchar(50),
    gateway_ref     varchar(50),
    paid_at         datetime,
    created_at      datetime default current_timestamp,
    updated_at      datetime default current_timestamp
);

-- SQLite has no ON UPDATE CURRENT_TIMESTAMP; this trigger is its equivalent.
-- The body stays on one line: byjg/migration splits a SQLite file on ";" + newline.
create trigger payment_updated_at
    after update on payment
    for each row
    when new.updated_at = old.updated_at
begin update payment set updated_at = current_timestamp where id = new.id; end;
