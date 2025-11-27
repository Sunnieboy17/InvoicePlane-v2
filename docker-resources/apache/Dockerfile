FROM httpd:2.4-alpine

# Enable required Apache modules for PHP-FPM proxying
RUN sed -i \
    -e 's/^#\(LoadModule proxy_module modules\/mod_proxy.so\)/\1/' \
    -e 's/^#\(LoadModule proxy_fcgi_module modules\/mod_proxy_fcgi.so\)/\1/' \
    -e 's/^#\(LoadModule rewrite_module modules\/mod_rewrite.so\)/\1/' \
    /usr/local/apache2/conf/httpd.conf

#Copy the config file
COPY ./config/invoiceplane-vhost.conf /usr/local/apache2/conf/extra/invoiceplane.conf
# Include the VirtualHost config
RUN echo "Include conf/extra/invoiceplane.conf" >> /usr/local/apache2/conf/httpd.conf
EXPOSE 80