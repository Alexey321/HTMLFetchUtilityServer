// HTMLFetchUtilityServer.cpp : This file contains the 'main' function. Program execution begins and ends there.
//


#include "pch.h"

#include <boost/beast/core.hpp>
#include <boost/beast/core/buffers_to_string.hpp>
#include <boost/beast/http.hpp>
#include <boost/beast/version.hpp>
#include <boost/asio/bind_executor.hpp>
#include <boost/asio/ip/tcp.hpp>
#include <boost/asio/strand.hpp>
#include <boost/config.hpp>
#include <boost/lexical_cast.hpp>
#include <boost/algorithm/string/replace.hpp>
#include <boost/regex.hpp>
#include <boost/asio/ssl/error.hpp>
#include <boost/asio/ssl/stream.hpp>
#include <boost/asio/connect.hpp>
#include <boost/asio/read_until.hpp>
#include <boost/asio/read.hpp>
#include <boost/asio/streambuf.hpp>
#include <boost/bind.hpp>
#include <boost/asio.hpp>
#include <boost/date_time/gregorian/gregorian.hpp>
#include <boost/date_time.hpp>
#include <boost/date_time/posix_time/posix_time.hpp>
//#include <boost/network/uri/uri.hpp>
//#include <boost/network/uri/uri_io.hpp>
#include <boost/property_tree/ptree.hpp>
#include <boost/property_tree/json_parser.hpp>
#include <algorithm>
#include <cstdlib>
#include <functional>
#include <iostream>
#include <memory>
#include <string>
#include <thread>
#include <vector>
#include <chrono>
#include <tuple>
#include <cctype>

namespace beast = boost::beast;         // from <boost/beast.hpp>
namespace http = beast::http;           // from <boost/beast/http.hpp>
namespace net = boost::asio;            // from <boost/asio.hpp>
namespace ssl = boost::asio::ssl;		// from <boost/asio/ssl/stream.hpp>
//namespace uri = boost::network::uri;	// from <boost/network/uri/uri.hpp>
namespace chrono = std::chrono;
using tcp = boost::asio::ip::tcp;			// from <boost/asio/ip/tcp.hpp>
using ptree = boost::property_tree::ptree;	// from <boost/property_tree/ptree.hpp>


/*

std::string parse_url(std::string& url)
{
	boost::regex ex("(http|https)://([^/ :]+):?([^/ ]*)(/?[^ #?]*)\\x3f?([^ #]*)#?([^ ]*)");
	boost::cmatch what;
	std::string ret = "";
	if (regex_match(url.c_str(), what, ex))
	{
		ret += "protocol: " + std::string(what[1].first, what[1].second) + "\r\n";
		ret += "domain:   " + std::string(what[2].first, what[2].second) + "\r\n";
		ret += "port:     " + std::string(what[3].first, what[3].second) + "\r\n";
		ret += "path:     " + std::string(what[4].first, what[4].second) + "\r\n";
		ret += "query:    " + std::string(what[5].first, what[5].second) + "\r\n";
		ret += "fragment: " + std::string(what[6].first, what[6].second) + "\r\n";
	}
}

*/

std::tuple<std::string, long long, std::string>
	get_web_page(
		std::string const& protocol, 
		std::string const& host_, 
		std::string const& ticker)
{
	// TODO: endure if else parts into a sepparate function
	if (protocol == "http")
	{
		chrono::high_resolution_clock::time_point t1 = chrono::high_resolution_clock::now();

		tcp::iostream stream;

		stream.connect(host_, "http");
		stream << "GET /" << ticker << " HTTP/1.1\r\n";
		stream << "Host: " << host_ << "\r\n";
		stream << "Cache-Control: no-cache\r\n";
		stream << "Connection: close\r\n\r\n" << std::flush;

		std::ostringstream os;
		
		os << stream.rdbuf();

		chrono::high_resolution_clock::time_point t2 = chrono::high_resolution_clock::now();
		auto duration = chrono::duration_cast<chrono::milliseconds>(t2 - t1).count();

		boost::posix_time::ptime timeUTC = boost::posix_time::second_clock::universal_time();

		return { os.str(), duration, boost::posix_time::to_iso_extended_string(timeUTC) };
	}
	else if (protocol == "https")
	{
		/*
		boost::system::error_code ec;

		net::io_context svc;
		ssl::context ctx(ssl::context::method::sslv23_client);
		ssl::stream<tcp::socket> ssock(svc, ctx);
		//ssock.lowest_layer().connect({ {}, 8087 });
		
		tcp::resolver resolver(svc);
		auto it = resolver.resolve({ host, "443" });
		net::connect(ssock.lowest_layer(), it);
		ssock.handshake(ssl::stream_base::handshake_type::client);
		
		// send request
		std::string request = "";
		request += "GET /" + ticker + " HTTP/1.1\r\n";
		request += "Host: " + host + "\r\n";
		request += "Cache-Control: no-cache\r\n";
		request += "Connection: close\r\n\r\n";
		write(ssock, net::buffer(request));
		
		// read response
		std::string response;
		chrono::high_resolution_clock::time_point t1 = chrono::high_resolution_clock::now();
		do {
			char buf[1024];
			//size_t bytes_transferred = ssock.read_some(net::buffer(buf), ec);
			//if (!ec) response.append(buf, buf + bytes_transferred);

			//size_t bytes_transferred = ssock.read_some(net::buffer(buf), ec);
			//if (!ec) response.append(buf, buf + bytes_transferred);
		} while (!ec);
		chrono::high_resolution_clock::time_point t2 = chrono::high_resolution_clock::now();
		auto duration = chrono::duration_cast<chrono::milliseconds>(t2 - t1).count();
		*/

		http::response<http::string_body> res;
		std::string header;
		long long duration;
		chrono::high_resolution_clock::time_point t1 = chrono::high_resolution_clock::now();
		// TODO: rewrite this part to a lover level construct for more control (emulating browser request header)
		// TODO: optimize,  add another, more rare, protocols support 
		try
		{

			std::string const host = host_;
			std::string const port = "443";//http: 80 https: 443
			std::string const target = "/" + ticker;
			const char* const ver = "1.1";
			int version = !std::strcmp("1.0", ver) ? 10 : 11;

			// The io_context is required for all I/O
			net::io_context ioc;

			// The SSL context is required, and holds certificates
			ssl::context ctx{ ssl::context::sslv23_client };

			// This holds the root certificate used for verification
			//load_root_certificates(ctx);
			ctx.set_default_verify_paths();

			// Verify the remote server's certificate
			ctx.set_verify_mode(ssl::verify_none);
			
			// These objects perform our I/O
			tcp::resolver resolver{ ioc };
			ssl::stream<tcp::socket> stream{ ioc, ctx };

			// Set SNI Hostname (many hosts need this to handshake successfully)
			if (!SSL_set_tlsext_host_name(stream.native_handle(), host.c_str()))
			{
				beast::error_code ec{ static_cast<int>(::ERR_get_error()), net::error::get_ssl_category() };
				throw beast::system_error{ ec };
			}
			
			// Look up the domain name
			auto const results = resolver.resolve(host, port);

			// Make the connection on the IP address we get from a lookup
			net::connect(stream.next_layer(), results.begin(), results.end());

			// Perform the SSL handshake
			stream.handshake(ssl::stream_base::client);

			// Set up an HTTP GET request message
			http::request<http::string_body> req{ http::verb::get, target, version };
			req.set(http::field::host, host);
			req.set(http::field::user_agent, BOOST_BEAST_VERSION_STRING);

			// Send the HTTP request to the remote host
			http::write(stream, req);

			// This buffer is used for reading and must be persisted
			beast::flat_buffer buffer;

			
			// Receive the HTTP response
			http::read(stream, buffer, res);

			std::stringstream ssheader;
			for (auto const& field : res)
				ssheader << field.name() << ": " << field.value() << "\r\n";
			ssheader << "\r\n";
			header = ssheader.str();
			// Write the message to standard out
			//for (auto const& field : res)
			//	std::cout << field.name() << ": " << field.value() << "\n";
			//std::cout << res.body() << std::endl;
			//std::cout << boost::beast::buffers_to_string(res.body().data()) << std::endl;

			// Gracefully close the stream
			beast::error_code ec;
			stream.shutdown(ec);
			stream.next_layer().close();
			if (ec == net::error::eof)
			{
				// Rationale:
				// http://stackoverflow.com/questions/25587403/boost-asio-ssl-async-shutdown-always-finishes-with-an-error
				ec.assign(0, ec.category());
			}
			if (ec)
				throw beast::system_error{ ec };

			chrono::high_resolution_clock::time_point t2 = chrono::high_resolution_clock::now();
			duration = chrono::duration_cast<chrono::milliseconds>(t2 - t1).count();

			boost::posix_time::ptime timeUTC = boost::posix_time::second_clock::universal_time();

			// If we get here then the connection is closed gracefully
			return { header + res.body(), duration, boost::posix_time::to_iso_extended_string(timeUTC) };
		}
		catch (std::exception const& e)
		{
			std::cerr << "Error: " << e.what() << std::endl;
			// Write the message to standard out
			//std::cout << res << std::endl;

			chrono::high_resolution_clock::time_point t2 = chrono::high_resolution_clock::now();
			duration = chrono::duration_cast<chrono::milliseconds>(t2 - t1).count();
			// some servers and clients do not close SSL connection, etc. 
			//we just ignore some errors and respond

			boost::posix_time::ptime timeUTC = boost::posix_time::second_clock::universal_time();

			return { header + res.body(), duration, boost::posix_time::to_iso_extended_string(timeUTC) };
		}

		return { "", 0, "" }; // should never happen
	}
	else
		return { "", 0, "" };
}

//------------------------------------------------------------------------------

// Report a failure
void
fail(beast::error_code ec, char const* what)
{
	// Attention ! We do not wand to see error messages right now
	//std::cerr << what << ": " << ec.message() << "\n";
}

//------------------------------------------------------------------------------
/*
namespace client
{
// Performs an HTTP GET and prints the response
class session : public std::enable_shared_from_this<session>
{
	tcp::resolver resolver_;
	ssl::stream<tcp::socket> stream_;
	beast::flat_buffer buffer_; // (Must persist between reads)
	http::request<http::empty_body> req_;
	http::response<http::string_body> res_;

public:
	// Resolver and stream require an io_context
	explicit
		session(net::io_context& ioc, ssl::context& ctx)
		: resolver_(ioc)
		, stream_(ioc, ctx)
	{
	}

	// Start the asynchronous operation
	void
		run(
			char const* host,
			char const* port,
			char const* target,
			int version)
	{
		// Set SNI Hostname (many hosts need this to handshake successfully)
		if (!SSL_set_tlsext_host_name(stream_.native_handle(), host))
		{
			beast::error_code ec{ static_cast<int>(::ERR_get_error()), net::error::get_ssl_category() };
			std::cerr << ec.message() << "\n";
			return;
		}

		// Set up an HTTP GET request message
		req_.version(version);
		req_.method(http::verb::get);
		req_.target(target);
		req_.set(http::field::host, host);
		req_.set(http::field::user_agent, BOOST_BEAST_VERSION_STRING);

		// Look up the domain name
		resolver_.async_resolve(
			host,
			port,
			std::bind(
				&session::on_resolve,
				shared_from_this(),
				std::placeholders::_1,
				std::placeholders::_2));
	}

	void
		on_resolve(
			beast::error_code ec,
			tcp::resolver::results_type results)
	{
		if (ec)
			return fail(ec, "resolve");

		// Make the connection on the IP address we get from a lookup
		net::async_connect(
			stream_.next_layer(),
			results.begin(),
			results.end(),
			std::bind(
				&session::on_connect,
				shared_from_this(),
				std::placeholders::_1));
	}

	void
		on_connect(beast::error_code ec)
	{
		if (ec)
			return fail(ec, "connect");

		// Perform the SSL handshake
		stream_.async_handshake(
			ssl::stream_base::client,
			std::bind(
				&session::on_handshake,
				shared_from_this(),
				std::placeholders::_1));
	}

	void
		on_handshake(beast::error_code ec)
	{
		if (ec)
			return fail(ec, "handshake");

		// Send the HTTP request to the remote host
		http::async_write(stream_, req_,
			std::bind(
				&session::on_write,
				shared_from_this(),
				std::placeholders::_1,
				std::placeholders::_2));
	}

	void
		on_write(
			beast::error_code ec,
			std::size_t bytes_transferred)
	{
		boost::ignore_unused(bytes_transferred);

		if (ec)
			return fail(ec, "write");

		// Receive the HTTP response
		http::async_read(stream_, buffer_, res_,
			std::bind(
				&session::on_read,
				shared_from_this(),
				std::placeholders::_1,
				std::placeholders::_2));
	}

	void
		on_read(
			beast::error_code ec,
			std::size_t bytes_transferred)
	{
		boost::ignore_unused(bytes_transferred);

		if (ec)
			return fail(ec, "read");

		// Write the message to standard out
		std::cout << res_ << std::endl;

		// Gracefully close the stream
		stream_.async_shutdown(
			std::bind(
				&session::on_shutdown,
				shared_from_this(),
				std::placeholders::_1));
	}

	void
		on_shutdown(beast::error_code ec)
	{
		if (ec == net::error::eof)
		{
			// Rationale:
			// http://stackoverflow.com/questions/25587403/boost-asio-ssl-async-shutdown-always-finishes-with-an-error
			ec.assign(0, ec.category());
		}
		if (ec)
			return fail(ec, "shutdown");

		// If we get here then the connection is closed gracefully
	}
};
}
*/
//------------------------------------------------------------------------------

// Return a reasonable mime type based on the extension of a file.
beast::string_view
mime_type(beast::string_view path)
{
	using beast::iequals;
	auto const ext = [&path]
	{
		auto const pos = path.rfind(".");
		if (pos == beast::string_view::npos)
			return beast::string_view{};
		return path.substr(pos);
	}();
	if (iequals(ext, ".htm"))  return "text/html";
	if (iequals(ext, ".html")) return "text/html";
	if (iequals(ext, ".php"))  return "text/html";
	if (iequals(ext, ".css"))  return "text/css";
	if (iequals(ext, ".txt"))  return "text/plain";
	if (iequals(ext, ".js"))   return "application/javascript";
	if (iequals(ext, ".json")) return "application/json";
	if (iequals(ext, ".xml"))  return "application/xml";
	if (iequals(ext, ".swf"))  return "application/x-shockwave-flash";
	if (iequals(ext, ".flv"))  return "video/x-flv";
	if (iequals(ext, ".png"))  return "image/png";
	if (iequals(ext, ".jpe"))  return "image/jpeg";
	if (iequals(ext, ".jpeg")) return "image/jpeg";
	if (iequals(ext, ".jpg"))  return "image/jpeg";
	if (iequals(ext, ".gif"))  return "image/gif";
	if (iequals(ext, ".bmp"))  return "image/bmp";
	if (iequals(ext, ".ico"))  return "image/vnd.microsoft.icon";
	if (iequals(ext, ".tiff")) return "image/tiff";
	if (iequals(ext, ".tif"))  return "image/tiff";
	if (iequals(ext, ".svg"))  return "image/svg+xml";
	if (iequals(ext, ".svgz")) return "image/svg+xml";
	return "application/text";
}

// Append an HTTP rel-path to a local filesystem path.
// The returned path is normalized for the platform.
std::string
path_cat(
	beast::string_view base,
	beast::string_view path)
{
	if (base.empty())
		return path.to_string();
	std::string result = base.to_string();
#if BOOST_MSVC
	char constexpr path_separator = '\\';
	if (result.back() == path_separator)
		result.resize(result.size() - 1);
	result.append(path.data(), path.size());
	for (auto& c : result)
		if (c == '/')
			c = path_separator;
#else
	char constexpr path_separator = '/';
	if (result.back() == path_separator)
		result.resize(result.size() - 1);
	result.append(path.data(), path.size());
#endif
	return result;
}


bool url_decode(const std::string& in, std::string& out)
{
	out.clear();
	out.reserve(in.size());
	for (std::size_t i = 0; i < in.size(); ++i)
	{
		if (in[i] == '%')
		{
			if (i + 3 <= in.size())
			{
				int value = 0;
				std::istringstream is(in.substr(i + 1, 2));
				if (is >> std::hex >> value)
				{
					out += static_cast<char>(value);
					i += 2;
				}
				else
				{
					return false;
				}
			}
			else
			{
				return false;
			}
		}
		else if (in[i] == '+')
		{
			out += ' ';
		}
		else
		{
			out += in[i];
		}
	}
	return true;
}

// This function produces an HTTP response for the given
// request. The type of the response object depends on the
// contents of the request, so the interface requires the
// caller to pass a generic lambda for receiving the response.
template<
	class Body, class Allocator,
	class Send>
	void
	handle_request(
		beast::string_view doc_root,
		http::request<Body, http::basic_fields<Allocator>>&& req,
		Send&& send)
{
	/*
	// Returns a bad request response
	auto const bad_request =
		[&req](beast::string_view why)
	{
		http::response<http::string_body> res{ http::status::bad_request, req.version() };
		res.set(http::field::server, BOOST_BEAST_VERSION_STRING);
		res.set(http::field::content_type, "text/html");
		res.keep_alive(req.keep_alive());
		res.body() = why.to_string();
		res.prepare_payload();
		return res;
	};

	// Returns a not found response
	auto const not_found =
		[&req](beast::string_view target)
	{
		http::response<http::string_body> res{ http::status::not_found, req.version() };
		res.set(http::field::server, BOOST_BEAST_VERSION_STRING);
		res.set(http::field::content_type, "text/html");
		res.keep_alive(req.keep_alive());
		res.body() = "The resource '" + target.to_string() + "' was not found.";
		res.prepare_payload();
		return res;
	};

	// Returns a server error response
	auto const server_error =
		[&req](beast::string_view what)
	{
		http::response<http::string_body> res{ http::status::internal_server_error, req.version() };
		res.set(http::field::server, BOOST_BEAST_VERSION_STRING);
		res.set(http::field::content_type, "text/html");
		res.keep_alive(req.keep_alive());
		res.body() = "An error occurred: '" + what.to_string() + "'";
		res.prepare_payload();
		return res;
	};

	// Make sure we can handle the method
	if (req.method() != http::verb::get &&
		req.method() != http::verb::head)
		return send(bad_request("Unknown HTTP-method"));

	// Request path must be absolute and not contain "..".
	if (req.target().empty() ||
		req.target()[0] != '/' ||
		req.target().find("..") != beast::string_view::npos)
		return send(bad_request("Illegal request-target"));

	// Build the path to the requested file
	std::string path = path_cat(doc_root, req.target());
	if (req.target().back() == '/')
		path.append("index.html");

	// Attempt to open the file
	beast::error_code ec;
	http::file_body::value_type body;
	body.open(path.c_str(), beast::file_mode::scan, ec);

	// Handle the case where the file doesn't exist
	if (ec == beast::errc::no_such_file_or_directory)
		return send(not_found(req.target()));

	// Handle an unknown error
	if (ec)
		return send(server_error(ec.message()));

	// Cache the size since we need it after the move
	auto const size = body.size();

	// Respond to HEAD request
	if (req.method() == http::verb::head)
	{
		http::response<http::empty_body> res{ http::status::ok, req.version() };
		res.set(http::field::server, BOOST_BEAST_VERSION_STRING);
		res.set(http::field::content_type, mime_type(path));
		res.content_length(size);
		res.keep_alive(req.keep_alive());
		return send(std::move(res));
	}

	// Respond to GET request
	http::response<http::file_body> res{
		std::piecewise_construct,
		std::make_tuple(std::move(body)),
		std::make_tuple(http::status::ok, req.version()) };
	res.set(http::field::server, BOOST_BEAST_VERSION_STRING);
	res.set(http::field::content_type, mime_type(path));
	res.content_length(size);
	res.keep_alive(req.keep_alive());
	return send(std::move(res));
	*/

	/*
	// The SSL context is required, and holds certificates
	ssl::context ctx{ ssl::context::sslv23_client };
	// This holds the root certificate used for verification
	load_root_certificates(ctx);
	// Verify the remote server's certificate
	ctx.set_verify_mode(ssl::verify_peer);
	// Launch the asynchronous operation
	client::session client_session(ioc, ctx);
	client_session->run(host, port, target, version);
	*/

	std::string target{ req.target() };
	//std::cout << "target: " << target << "\r\n";

	http::response<http::string_body> res{ http::status::not_found, req.version() };
	res.set(http::field::server, BOOST_BEAST_VERSION_STRING);
	res.set(http::field::content_type, "text/html");
	res.keep_alive(req.keep_alive());

	// TODO: IMPORTANT !!!!!!!!!! handle all possible exeptions ( if there are some ) and server fuzzing attacks. Possible vulnerability here
	
	/*
	// parse target path
	size_t start = target.find("/?protocol=");
	size_t end = target.find("&");
	if (start != std::string::npos && end != std::string::npos)
	{
		start += 11; // /?protocol=
		std::string protocol = target.substr(start, end - start);
		
		start = target.find("&domain=", end);
		end = target.find("&", end + 1);
		if (start != std::string::npos && end != std::string::npos)
		{
			start += 8; // &domain=
			std::string domain = target.substr(start, end - start);
			
			start = target.find("&ticker=", end);
			if (start != std::string::npos)
			{
				start += 8; // &ticker=
				std::string ticker = target.substr(start);

				//std::cout << "protocol: " << protocol << "\r\n";
				//std::cout << "domain: " << domain << "\r\n";
				//std::cout << "ticker: " << ticker << "\r\n";

				std::string page_source;
				long long duration;
				std::string fetch_time;						 //get_web_page("https", "colnect.com", "en");
				std::tie(page_source, duration, fetch_time) = get_web_page(protocol, domain, ticker);
				std::string resporce = "";
				resporce += page_source + "\r\n\r\n" + std::to_string(duration) + "ms\r\n\r\n" + fetch_time;
				res.body() = resporce;
			}
			else
			{
				res.body() = "";
			}
		}
		else
		{
			res.body() = "";
		}
	}
	else
	{
		res.body() = "";
	}
	*/

	/*
	// This did not compiled, seems deprecated
	// parse target path
	size_t start = target.find("/?url=");
	if (start != std::string::npos)
	{
		start += 6;
		std::string url = target.substr(start);
		uri::uri instance(url);
		if (instance.is_valid())
		{
			std::cout << "scheme: " << instance.scheme() << "\r\n";
			std::cout << "host: " << instance.host() << "\r\n";
			std::cout << "path: " << instance.path() << "\r\n";

			std::string page_source;
			long long duration;
			std::string fetch_time;						 //get_web_page("https", "colnect.com", "en");
			std::tie(page_source, duration, fetch_time) = get_web_page(instance.scheme(), instance.host(), instance.path());
			std::string resporce = "";
			resporce += page_source + "\r\n\r\n" + std::to_string(duration) + "ms\r\n\r\n" + fetch_time;
			res.body() = resporce;
		}
		else
		{
			res.body() = "Error: invalid url";
		}
	}
	else
	{
		res.body() = "Error: invalid request";
	}
	*/

	size_t start = target.find("/?url=");
	if (start != std::string::npos)
	{
		start += 6;
		std::string url_encoded = target.substr(start);
		size_t nextparam = url_encoded.find("&");
		if (nextparam != std::string::npos)
		{
			url_encoded = url_encoded.substr(0, nextparam);
		}
		std::string url;
		if (url_decode(url_encoded, url))
		{
			boost::regex regex("(http|https)://([^/ :]+):?([^/ ]*)(/?[^ #?]*)\\x3f?([^ #]*)#?([^ ]*)");
			boost::cmatch what;
			if (regex_match(url.c_str(), what, regex))
			{
				std::string protocol = std::string(what[1].first, what[1].second);
				std::string domain = std::string(what[2].first, what[2].second);
				std::string port = std::string(what[3].first, what[3].second);
				std::string path = std::string(what[4].first, what[4].second);
				std::string query = std::string(what[5].first, what[5].second);
				std::string fragment = std::string(what[6].first, what[6].second);

				//std::cout << "protocol: " << protocol << "\r\n";
				//std::cout << "domain:   " << domain << "\r\n";
				//std::cout << "port:     " << port << "\r\n";
				//std::cout << "path:     " << path << "\r\n";
				//std::cout << "query:    " << query << "\r\n";
				//std::cout << "fragment: " << fragment << "\r\n"; // TODO: improve get_web_page(...) urls variation handling

				if (path != "")
				{
					path = path.substr(1); // format for get_web_page(...) function
				}

				std::string page_source;
				long long duration;
				std::string fetch_time;						 //get_web_page("https", "colnect.com", "en");
				std::tie(page_source, duration, fetch_time) = get_web_page(protocol, domain, path);
				std::string resporce = "";

				//-------
				// constructing json
				ptree result; // TODO: add adequate res str formatting, optimize json construction, return appropriate variables from get_web_page(...) right away
				result.put("requested_uri", url);
				result.put("duration_ms", duration);
				result.put("fetch_time_UTC", fetch_time);
				if (page_source != "")
				{
					boost::regex regex1("^$");
					boost::smatch what1;
					if (boost::regex_search(page_source, what1, regex1))
					{
						std::string header = std::string(page_source.cbegin(), what1[0].first);
						std::string content = std::string(what1[0].second, page_source.cend());
						result.put("header", header);
						result.put("content", content);
						result.put("status", "ok");
						std::stringstream ss;
						boost::property_tree::json_parser::write_json(ss, result);
						res.body() = ss.str();
					}
				}
				else
				{
					result.put("status", "empty responce"); // TODO: endure invalid status responce chunks like this into a sepparate function
					std::stringstream ss;
					boost::property_tree::json_parser::write_json(ss, result);
					res.body() = ss.str();
				}

				//-------

				//resporce += page_source + "\r\n\r\n" + std::to_string(duration) + "ms\r\n\r\n" + fetch_time;
				//res.body() = resporce;

			}
			else
			{
				ptree result;
				result.put("status", "invalid url");
				std::stringstream ss;
				boost::property_tree::json_parser::write_json(ss, result);
				res.body() = ss.str();
			}
		}
		else
		{
			ptree result;
			result.put("status", "can't decode url");
			std::stringstream ss;
			boost::property_tree::json_parser::write_json(ss, result);
			res.body() = ss.str();
		}
	}
	else
	{
		ptree result;
		result.put("status", "invalid request");
		std::stringstream ss;
		boost::property_tree::json_parser::write_json(ss, result);
		res.body() = ss.str();
	}

	res.prepare_payload();
	send(std::move(res));

}

//------------------------------------------------------------------------------

// Handles an HTTP server connection
class session : public std::enable_shared_from_this<session>
{
	// This is the C++11 equivalent of a generic lambda.
	// The function object is used to send an HTTP message.
	struct send_lambda
	{
		session& self_;

		explicit
			send_lambda(session& self)
			: self_(self)
		{
		}

		template<bool isRequest, class Body, class Fields>
		void
			operator()(http::message<isRequest, Body, Fields>&& msg) const
		{
			// The lifetime of the message has to extend
			// for the duration of the async operation so
			// we use a shared_ptr to manage it.
			auto sp = std::make_shared<
				http::message<isRequest, Body, Fields>>(std::move(msg));

			// Store a type-erased version of the shared
			// pointer in the class to keep it alive.
			self_.res_ = sp;

			// Write the response
			http::async_write(
				self_.socket_,
				*sp,
				net::bind_executor(
					self_.strand_,
					std::bind(
						&session::on_write,
						self_.shared_from_this(),
						std::placeholders::_1,
						std::placeholders::_2,
						sp->need_eof())));
		}
	};

	tcp::socket socket_;
	net::strand<
		net::io_context::executor_type> strand_;
	beast::flat_buffer buffer_;
	std::shared_ptr<std::string const> doc_root_;
	http::request<http::string_body> req_;
	std::shared_ptr<void> res_;
	send_lambda lambda_;

public:
	// Take ownership of the socket
	explicit
		session(
			tcp::socket socket,
			std::shared_ptr<std::string const> const& doc_root)
		: socket_(std::move(socket))
		, strand_(socket_.get_executor())
		, doc_root_(doc_root)
		, lambda_(*this)
	{
	}

	// Start the asynchronous operation
	void
		run()
	{
		do_read();
	}

	void
		do_read()
	{
		// Make the request empty before reading,
		// otherwise the operation behavior is undefined.
		req_ = {};

		// Read a request
		http::async_read(socket_, buffer_, req_,
			net::bind_executor(
				strand_,
				std::bind(
					&session::on_read,
					shared_from_this(),
					std::placeholders::_1,
					std::placeholders::_2)));
	}

	void
		on_read(
			beast::error_code ec,
			std::size_t bytes_transferred)
	{
		boost::ignore_unused(bytes_transferred);

		// This means they closed the connection
		if (ec == http::error::end_of_stream)
			return do_close();

		if (ec)
			return fail(ec, "read");

		// Send the response
		handle_request(*doc_root_, std::move(req_), lambda_);
	}

	void
		on_write(
			beast::error_code ec,
			std::size_t bytes_transferred,
			bool close)
	{
		boost::ignore_unused(bytes_transferred);

		if (ec)
			return fail(ec, "write");

		if (close)
		{
			// This means we should close the connection, usually because
			// the response indicated the "Connection: close" semantic.
			return do_close();
		}

		// We're done with the response so delete it
		res_ = nullptr;

		// Read another request
		do_read();
	}

	void
		do_close()
	{
		// Send a TCP shutdown
		beast::error_code ec;
		socket_.shutdown(tcp::socket::shutdown_send, ec);

		// At this point the connection is closed gracefully
	}
};

//------------------------------------------------------------------------------

// Accepts incoming connections and launches the sessions
class listener : public std::enable_shared_from_this<listener>
{
	tcp::acceptor acceptor_;
	tcp::socket socket_;
	std::shared_ptr<std::string const> doc_root_;

public:
	listener(
		net::io_context& ioc,
		tcp::endpoint endpoint,
		std::shared_ptr<std::string const> const& doc_root)
		: acceptor_(ioc)
		, socket_(ioc)
		, doc_root_(doc_root)
	{
		beast::error_code ec;

		// Open the acceptor
		acceptor_.open(endpoint.protocol(), ec);
		if (ec)
		{
			fail(ec, "open");
			return;
		}

		// Allow address reuse
		acceptor_.set_option(net::socket_base::reuse_address(true), ec);
		if (ec)
		{
			fail(ec, "set_option");
			return;
		}

		// Bind to the server address
		acceptor_.bind(endpoint, ec);
		if (ec)
		{
			fail(ec, "bind");
			return;
		}

		// Start listening for connections
		acceptor_.listen(
			net::socket_base::max_listen_connections, ec);
		if (ec)
		{
			fail(ec, "listen");
			return;
		}
	}

	// Start accepting incoming connections
	void
		run()
	{
		if (!acceptor_.is_open())
			return;
		do_accept();
	}

	void
		do_accept()
	{
		acceptor_.async_accept(
			socket_,
			std::bind(
				&listener::on_accept,
				shared_from_this(),
				std::placeholders::_1));
	}

	void
		on_accept(beast::error_code ec)
	{
		if (ec)
		{
			fail(ec, "accept");
		}
		else
		{
			// Create the session and run it
			std::make_shared<session>(
				std::move(socket_),
				doc_root_)->run();
		}

		// Accept another connection
		do_accept();
	}
};

//------------------------------------------------------------------------------

int main(int argc, char* argv[])
{
	// Check command line arguments.
	if (argc != 5)
	{
		std::cerr <<
			"Usage: HTMLFetchUtilityServer <address> <port> <doc_root> <threads>\n" <<
			"Example:\n" <<
			"    HTMLFetchUtilityServer 127.0.0.1 777 . 1\n";
		return EXIT_FAILURE;
	}
	auto const address = net::ip::make_address(argv[1]);
	auto const port = static_cast<unsigned short>(std::atoi(argv[2]));
	auto const doc_root = std::make_shared<std::string>(argv[3]);
	auto const threads = std::max<int>(1, std::atoi(argv[4]));

	// The io_context is required for all I/O
	net::io_context ioc{ threads };

	// Create and launch a listening port
	std::make_shared<listener>(
		ioc,
		tcp::endpoint{ address, port },
		doc_root)->run();

	// Run the I/O service on the requested number of threads
	std::vector<std::thread> v;
	v.reserve(threads - 1);
	for (auto i = threads - 1; i > 0; --i)
		v.emplace_back(
			[&ioc]
	{
		ioc.run();
	});
	ioc.run();

	return EXIT_SUCCESS;
}