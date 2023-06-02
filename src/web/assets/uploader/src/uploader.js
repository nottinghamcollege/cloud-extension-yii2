/** global: Craft */
/** global: Garnish */

const getUploadUrl = function(data) {
  const formData = data.formData;
  const file = data.files[0];

  return Craft.sendActionRequest('POST', 'cloud/get-upload-url', {
    data: Object.assign(formData, {
      file: {
        lastModified: file.lastModified,
        name: file.name,
        size: file.size,
        type: file.type,
      },
    }),
  }).then(() => data.submit());
}

Craft.CloudUploader = Craft.Uploader.extend({
  init: function ($element, settings) {
    this.base($element, settings);
    this.uploader.on('fileuploadsubmit', this.onSubmit.bind(this));
    this.uploader.on('fileuploaddone', this.onDone.bind(this));
  },
  onDone: function(event, data) {
    data.jqXHR = axios.post(
      Craft.getActionUrl('cloud/create-asset'),
      data.formData,
    );
  },
  onSubmit: function(event, data) {
    const formData = this.uploader.fileupload('option', 'formData');
    const file = data.files[0];
    const filename = file.name;
    Object.assign(formData, {filename});

    data.jqXHR = Craft.sendActionRequest('POST', 'cloud/get-upload-url', {
      data: formData
    }).then((response) => {
      Object.assign(formData, response.data);

      return this.uploader.fileupload('send', {
        url: response.data.url,
        type: 'PUT',
        files: [file],
      });

      // Upload works but doesn't trigger stuff…
      // return axios.put(response.data.url, file, {
      //   headers: {
      //     'Content-Type': file.type,
      //   }
      // }).then(() => axios.post(
      //   Craft.getActionUrl('cloud/create-asset'),
      //   formData,
      // ));
    });

    return false;
  }
});
