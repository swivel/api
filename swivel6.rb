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
    FasterCSV.parse get("sheets/#{sheet['id']}.csv")
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
    resp = post(path + ".json", xml).body
    JSON.parse resp
  end

  def api_put path, hash = {}
    xml = XmlSimple.xml_out hash, 'keeproot' => true, 'noattr' => true
    resp = put(path + ".json", xml).body
    JSON.parse resp if resp.strip != ''
  end

  def api_get path, params = {}
    ret = []
    json = nil
    page = 1
    max_page = 1_000
    begin
      resp = get path + ".json", params.merge(:page => page)

      begin
        json = JSON.parse resp.body
      rescue
        puts path
        puts resp.body
      end

      return json unless json.is_a? Array

      ret.concat json

      should_continue =
        if resp['content-range'] && resp['content-range'] =~ /pages \d+-(\d+)\/(\d+)/
          page = $1.to_i
          max_page = $2.to_i
          page < max_page
        else
          # hack for pages that don't have content-range
          json.length == 30
        end

      page += 1
    end while should_continue
    ret
  end

  def get path, params = {}
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
    uri.query = hash_to_param_string(opts[:params]) if opts[:params]

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
      http.request req
    end
  end

  def hash_to_param_string hash
    hash.keys.map{ |k| CGI.escape(k.to_s) + '=' + CGI.escape(hash[k].to_s) }.join('&');
  end
end