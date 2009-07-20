require 'xmlsimple'
require 'uri'
require 'net/https'
require 'json'

class Swivel6
  def initialize user, password, group, root_url = 'https://api.swivel.com/v1'
    @user = user
    @password = password
    @group = group
    @root_url = root_url
    @uri = URI.parse(root_url)
    @http = Net::HTTP.new @uri.host, @uri.port
    if @uri.scheme == 'https'
      @http.use_ssl = true
      @http.verify_mode = OpenSSL::SSL::VERIFY_NONE
    end
  end

  def workbooks
    api_get "groups/#{@group}/workbooks"
  end

  def workbook_sheets workbook
    api_get "workbooks/#{workbook['id']}/sheets"
  end

  def sheet_data sheet
    get_data "tabulars/#{sheet['id']}/cells"
  end

  def charts
    api_get "groups/#{@group}/charts"
  end

  def chart_data chart
    FasterCSV.parse get("charts/#{chart['id']}.csv")
  end

  def create_chart
    api_post "groups/#{@group}/charts", 'chart' => { 'name' => 'API Created Chart' }
  end

  def update_chart chart, hash
    api_put "groups/#{@group}/charts/#{chart['id']}", 'chart' => hash
  end

  def clear_chart_data chart
    api_put "charts/#{chart['id']}", 'chart' => { 'data' => '' }
  end

  def set_chart_data chart, data
    api_put "charts/#{chart['id']}", 'chart' => { 'data' => data.map{|row| row.to_csv}.join }
  end

private
  def api_post path, hash = {}
    xml = XmlSimple.xml_out hash, 'keeproot' => true, 'noattr' => true
    resp = post path + ".json", xml
    JSON.parse resp
  end

  def api_put path, hash = {}
    xml = XmlSimple.xml_out hash, 'keeproot' => true, 'noattr' => true
    resp = put path + ".json", xml
    JSON.parse resp if resp.strip != ''
  end

  def api_get path
    ret = []
    json = nil
    page = 1
    begin
      resp = get path + ".json", "page=#{page}"
      begin
        json = JSON.parse resp
      rescue
        puts path
        puts resp
      end

      return json unless json.is_a? Array

      ret.concat json
      page += 1
    end while json.size == 1000
    ret
  end

  def get path, params = nil
    request 'get', path, :params => params
  end

  def post path, xml
    request 'post', path, :xml => xml
  end

  def put path, xml
    request 'put', path, :xml => xml
  end

  def request req, path, opts = {}
    uri = @uri.merge path
    uri.query = opts[:params] if opts[:params]

    puts "#{req} #{uri.request_uri}"
    req_class = case req
      when "post"
        Net::HTTP::Post
      when "put"
        Net::HTTP::Put
      when "get"
        Net::HTTP::Get
      end
    req = req_class.new uri.request_uri

    if opts[:xml]
      req.set_content_type 'text/xml'
      req.body = opts[:xml]
    end

    req.basic_auth @user, @password

    @http.start do |http|
      http.request(req).body
    end
  end

  def get_data path
    cells = api_get path

    num_rows = cells.map{|c| c['r'].to_i}.max + 1 rescue 0
    num_cols = cells.map{|c| c['c'].to_i}.max + 1 rescue 0

    data = Array.new(num_rows) do
      Array.new(num_cols)
    end

    cells.each do |cell|
      data[cell['r']][cell['c']] = cell['formatted']
    end

    data
  end

  def get_chart_grid_id chart
    if chart['grid_id'].nil?
      json = api_get "/groups/#{@group}/charts/#{chart['id']}/grid"
      chart['grid_id'] = json['id'].to_i
    end
    chart['grid_id']
  end
end