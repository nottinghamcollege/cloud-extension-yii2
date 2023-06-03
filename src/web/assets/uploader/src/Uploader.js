/** global: Craft */
/** global: Garnish */

const getUploadUrl = function (data) {
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
};

Craft.CloudUploader = Craft.Uploader.extend({
  init: function ($element, settings) {
    this.base($element, settings);
    this.uploader.off('fileuploadadd');
    this.uploader = null;
    this.formData = {};
    this.$fileInput = this.$element.prev();
    this.$fileInput.on('change', this.upload.bind(this));
  },

  /**
   * Set uploader parameters.
   */
  setParams: function (paramObject) {
    // If CSRF protection isn't enabled, these won't be defined.
    if (
      typeof Craft.csrfTokenName !== 'undefined' &&
      typeof Craft.csrfTokenValue !== 'undefined'
    ) {
      // Add the CSRF token
      paramObject[Craft.csrfTokenName] = Craft.csrfTokenValue;
    }

    this.formData = paramObject;
  },
  upload: async function (event) {
    const files = event.target.files;
    const file = files[0];

    Object.assign(this.formData, {
      filename: file.name,
    });

    this.$element.trigger('fileuploadstart');

    try {
      let response = await Craft.sendActionRequest(
        'POST',
        'cloud/get-upload-url',
        {
          data: this.formData,
        }
      );

      Object.assign(this.formData, response.data);

      await axios.put(response.data.url, file, {
        headers: {
          'Content-Type': file.type,
        },
        onUploadProgress: (axiosProgressEvent) => {
          this.$element.trigger('fileuploadprogressall', [axiosProgressEvent]);
        },
      });

      response = await axios.post(
        Craft.getActionUrl('cloud/create-asset'),
        this.formData
      );
      this.$element.trigger('fileuploaddone', [response.data]);
    } catch (err) {
      this.$element.trigger('fileuploadfail', [{
        message: err.message,
        filename: file.name,
      }]);
    } finally {
      this.$element.trigger('fileuploadalways');
    }
  },

  /**
   * Get the number of uploads in progress.
   */
  getInProgress: function () {
    return 0;
  },
});

// Register it!
// Craft.registerAssetUploaderClass('craft\\cloud\\AssetFs', Craft.CloudUploader);
