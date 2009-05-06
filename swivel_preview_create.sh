# Usage: sh swivel_preview_create.sh email@address.com password data_set_data.tsv
EMAIL=$1
PASSWORD=$2
DATA_SET_DATA=$3

echo "$EMAIL $PASSWORD $DATA_SET_DATA"

# log in
curl -X POST -s -c /tmp/swivel_update_cookies.txt -d "email=${EMAIL}" -d "password=${PASSWORD}" -L 'http://www.swivel.com/security/login' > /dev/null 2>&1

# upload the file
DATA_SET_ID=`curl -X POST -s -L -b /tmp/swivel_update_cookies.txt \
  -F "uploaded_text_area[text_area]=<${DATA_SET_DATA}" \
  -F "continue=Begin Upload >" \
  "http://www.swivel.com/upload/update_upload?upload_type=type_in" | perl -ne 'print $1 if($_ =~ /\/upload\/update\_preview\/(\d+)/)'`

# set the settings on the file
# use the first line for the column titles
# (for comma-delimited use 'uploaded_file[column_separator]=,')
curl -X POST -s -L -b /tmp/swivel_update_cookies.txt \
 -d 'uploaded_file[column_separator]=\t' \
 -d 'uploaded_file[first_line_number]=1' \
 -d 'uploaded_file[first_line_titles]=true' \
 -d 'continue=Continue >' \
 "http://www.swivel.com/upload/update_preview/${DATA_SET_ID}?upload_type=type_in" > /dev/null 2>&1

# update the column types and headers (assumes the defaults are correct)
curl -X POST -s -L -b /tmp/swivel_update_cookies.txt \
  -d 'continue=Continue >' \
  "http://www.swivel.com/upload/update_alter/${DATA_SET_ID}?upload_type=type_in" > /dev/null 2>&1

# citation is the data source
# citation_url is the link to the data source

# tags can be:
#   economics, entertainment, health, politics, science, society
#   sports, technology, miscellaneous
# add additional tags with additional:
#   -d 'uploaded_data_set[category_tags][]=tag' \
curl -X POST -s -L -b /tmp/swivel_update_cookies.txt \
 -d "uploaded_data_set[name]=${DATA_SET_DATA}" \
 -d "uploaded_data_set[citation]=${DATA_SET_DATA}" \
 -d "uploaded_data_set[citation_url]=" \
 -d 'uploaded_data_set[category_tags][]=miscellaneous' \
 -d 'continue=Continue >' \
 "http://www.swivel.com/upload/update_describe/${DATA_SET_ID}?upload_type=type_in" > /dev/null 2>&1

DATA_SET_ID=`curl -X POST -s -L -b /tmp/swivel_update_cookies.txt \
 -d 'uploaded_data_set[image_url]=' \
 -d 'finish=Finish' \
 "http://www.swivel.com/upload/update_flickrize/${DATA_SET_ID}?upload_type=type_in" | perl -ne 'print $1 if($_ =~ /h1.*?\/data\_sets\/show\/(\d+).*?\/h1/)'`

echo "Data available at: http://www.swivel.com/data_sets/show/${DATA_SET_ID}"