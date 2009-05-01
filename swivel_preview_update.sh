# Usage: sh swivel_update.sh email@address.com password 12345 data_set_data.tsv
EMAIL=$1
PASSWORD=$2
DATA_SET_ID=$3
DATA_SET_DATA=$4

echo "$EMAIL $PASSWORD $DATA_SET_ID $DATA_SET_DATA"

# log in
curl -X POST -s -c /tmp/swivel_update_cookies.txt -d "email=${EMAIL}" -d "password=${PASSWORD}" -L 'http://www.swivel.com/security/login' > /dev/null 2>&1

# upload the file
UPLOADED_FILE_ID=`curl -X POST -s -L -b /tmp/swivel_update_cookies.txt \
  -F "uploaded_text_area[text_area]=<${DATA_SET_DATA}" \
  -F "continue=Begin Update >" \
  "http://www.swivel.com/update/update_upload/${DATA_SET_ID}?upload_type=type_in" | perl -ne 'print $1 if($_ =~ /uploaded_file_id=(\d+)/)'`

echo "$UPLOADED_FILE_ID"

# set the settings on the file
# (for comma-delimited use 'uploaded_file[column_separator]=,')
curl -X POST -s -L -b /tmp/swivel_update_cookies.txt \
 -d 'uploaded_file[column_separator]=\t' \
 -d 'uploaded_file[first_line_number]=1' \
 -d 'uploaded_file[first_line_titles]=false' \
 -d 'continue=Continue >' \
 "http://www.swivel.com/update/update_preview/${DATA_SET_ID}?upload_type=type_in&uploaded_file_id=${UPLOADED_FILE_ID}" > /dev/null 2>&1

# append the data
# (to append use -d 'append=Append +')
# (to replace use -d 'replace=Replace -/+')
curl -X POST -s -L -b /tmp/swivel_update_cookies.txt \
  -d 'append=Append +' \
  "http://www.swivel.com/update/update_alter/${DATA_SET_ID}?uploaded_file_id=${UPLOADED_FILE_ID}&upload_type=type_in" > /dev/null 2>&1
