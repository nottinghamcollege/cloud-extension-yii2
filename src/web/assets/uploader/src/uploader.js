/** global: Craft */
/** global: Garnish */
Craft.CloudUploader = Craft.Uploader.extend({
  init: function ($element, settings) {
    this.base($element, settings);
    this.uploader.on('fileuploadsubmit', this.onSubmit.bind(this));
  },
  onSubmit: function (e, data) {
    const file = data.files[0];
    const formData = this.uploader.fileupload('option', 'formData');

    data.jqXHR = Craft.sendActionRequest('POST', 'cloud/get-upload-url', {
      data: {
        file: {
          lastModified: file.lastModified,
          name: file.name,
          size: file.size,
          type: file.type,
        },
      },
    })
      .then((response) => {
        Object.assign(formData, response.data);
        Object.assign(data, {
          type: 'PUT',
          url: response.data.url,
        });

        return this.uploader.fileupload('send', data);
      })
      .then(() => {
        return $.ajax({
          url: Craft.getActionUrl('cloud/create-asset'),
          type: 'POST',
          headers: this.uploader.fileupload('option', 'headers'),
          data: formData,
        });
      });

    return false;
  },
});
