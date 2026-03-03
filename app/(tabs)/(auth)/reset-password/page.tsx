import axios from "axios";
import { Link, useLocalSearchParams, useRouter } from "expo-router";
import React from "react";
import { Image, Pressable, SafeAreaView, Text, TextInput, View } from "react-native";
import { SafeAreaProvider } from "react-native-safe-area-context";
import NiceAlert from "../../../../components/NiceAlert/NiceAlert";
import "../../../../style/global.css";

export default function ResetPassword() {
  const params = useLocalSearchParams();
  const tokenParam = typeof params.token === "string" ? params.token : "";

  const [token, setToken] = React.useState(tokenParam);
  const [senha, setSenha] = React.useState("");
  const [confirmar, setConfirmar] = React.useState("");

  const [alertVisible, setAlertVisible] = React.useState(false);
  const [alertMessage, setAlertMessage] = React.useState("");
  const [alertTitle, setAlertTitle] = React.useState("Ocorreu um erro");

  const router = useRouter();

  function showAlert(message: string, title = "Ocorreu um erro") {
    setAlertTitle(title);
    setAlertMessage(message);
    setAlertVisible(true);
  }

  async function handleResetPassword() {
    if (!token) {
      showAlert("Cole o token enviado no e-mail.");
      return;
    }
    if (senha !== confirmar) {
      showAlert("As senhas não coincidem.");
      return;
    }

    try {
      //const response = await axios.post("http://localhost/SICAD_Oficial/controller/reset_password.php", {
      const response = await axios.post("https://sicad.linceonline.com.br/controller/reset_password.php", {
        token: token,
        password: senha,
      });

      if (response.data?.success) {
        showAlert(response.data.message ?? "Senha atualizada!", "Tudo certo");
        router.push("/(tabs)/(auth)/signin/page");
      } else {
        showAlert(response.data?.message ?? "Não foi possível redefinir agora.");
      }
    } catch (error) {
      console.error("Erro na requisicao: ", error);
      showAlert("Erro de conexão. Tente novamente.");
    }
  }

  return (
    <View className="flex-1 flex-row bg-white dark:bg-[#121212]">
      <View id="aside" className=" w-5/12">
        <Image
          source={require("../../../../assets/images/side-view-login-cadastro.png")}
          style={{ width: "100%" }}
          className=" mobile:h-0 mobile:w-0 mobile:hidden"
        />
      </View>

      <View className="flex-1 items-center justify-center">
        <View>
          <Image source={require("../../../../assets/images/logo-composta.png")} className="mb-4" />
          <Text className="text-6xl dark:color-white">Redefinir senha</Text>

          <Text className="text-2xl dark:color-white mt-2">
            Digite o token enviado no e-mail e sua nova senha.
          </Text>

          <View className="flex flex-col gap-1 mt-4">
            <View>
              <Text className="text-2xl dark:color-white">Token</Text>
              <SafeAreaProvider>
                <SafeAreaView>
                  <TextInput
                    value={token}
                    onChangeText={setToken}
                    autoCapitalize="none"
                    className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
                  />
                </SafeAreaView>
              </SafeAreaProvider>
            </View>

            <View>
              <Text className="text-2xl dark:color-white">Nova senha</Text>
              <SafeAreaProvider>
                <SafeAreaView>
                  <TextInput
                    secureTextEntry={true}
                    value={senha}
                    onChangeText={setSenha}
                    className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
                  />
                </SafeAreaView>
              </SafeAreaProvider>
            </View>

            <View>
              <Text className="text-2xl dark:color-white">Confirmar senha</Text>
              <SafeAreaProvider>
                <SafeAreaView>
                  <TextInput
                    secureTextEntry={true}
                    value={confirmar}
                    onChangeText={setConfirmar}
                    className="w-full h-12 bg-transparent border border-slate-700 rounded-xl dark:color-white text-lg px-4"
                  />
                </SafeAreaView>
              </SafeAreaProvider>
            </View>

            <Pressable
              onPress={handleResetPassword}
              className="w-full h-12 rounded-xl bg-green-600 text-2xl font-bold mt-4 color-white justify-center items-center"
            >
              <Text className="color-white text-xl">Salvar nova senha</Text>
            </Pressable>

            <Link href="/(tabs)/(auth)/signin/page" className="dark:color-white underline text-xl mt-4">
              Voltar para o login
            </Link>
          </View>
        </View>
      </View>

      <NiceAlert
        visible={alertVisible}
        title={alertTitle}
        message={alertMessage}
        onClose={() => setAlertVisible(false)}
      />
    </View>
  );
}