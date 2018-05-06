#! /bin/bash -

set -e

CLUSTER_USERNAME=username
CLUSTER_PASSWORD=password

BUCKET_NAME=bucket
RBAC_USERNAME=rusername
RBAC_PASSWORD=rpassword

function init_cluster() {
    couchbase-cli cluster-init \
        --cluster couchbase://localhost \
        --cluster-username $CLUSTER_USERNAME \
        --cluster-password $CLUSTER_PASSWORD
}

function init_bucket() {
    couchbase-cli bucket-create \
        --cluster couchbase://localhost \
        --username $CLUSTER_USERNAME \
        --password $CLUSTER_PASSWORD \
        --bucket $BUCKET_NAME \
        --bucket-type couchbase \
        --bucket-ramsize 100 \
        --enable-flush 1 \
        --wait
}

function init_user() {
    couchbase-cli user-manage \
        --cluster couchbase://localhost \
        --username $CLUSTER_USERNAME \
        --password $CLUSTER_PASSWORD \
        --set \
        --rbac-username $RBAC_USERNAME \
        --rbac-password $RBAC_PASSWORD \
        --rbac-name $RBAC_USERNAME \
        --roles bucket_full_access[$BUCKET_NAME] \
        --auth-domain local
}

init_cluster || true
init_bucket || true
init_user || true
